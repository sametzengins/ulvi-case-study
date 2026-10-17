<?php

namespace App\Jobs;

use App\Actions\FailMessage;
use App\Conversations\ReplyFailedPermanently;
use App\Conversations\ReplyGenerator;
use App\Enums\LedgerEntryType;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Events\AgentResponded;
use App\Models\Message;
use App\Wallet\TokenWallet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Produces the reply for one message. If it runs twice, the message still gets one reply
 * and one refund: the status is checked again under a lock, reply_to_id is unique and the
 * refund key is the message.
 */
final class GenerateReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    // Lower than Redis retry_after (90s), so a slow attempt is not given to a second worker.
    public int $timeout = 60;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Message $message) {}

    /**
     * Seconds to wait before the second and third attempt.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [2, 10];
    }

    public function handle(ReplyGenerator $generator, TokenWallet $wallet): void
    {
        $message = Message::query()->with('conversation.character')->find($this->message->id);

        if ($message === null || $message->status->isFinal()) {
            return;
        }

        $message->update(['status' => MessageStatus::Processing]);

        try {
            $reply = $generator->generate($message);
        } catch (ReplyFailedPermanently $e) {
            $this->fail($e);

            return;
        }

        DB::transaction(function () use ($message, $reply, $wallet) {
            $message = Message::query()->with('user')->lockForUpdate()->findOrFail($message->id);

            if ($message->status->isFinal()) {
                return;
            }

            $answer = Message::query()->create([
                'conversation_id' => $message->conversation_id,
                'user_id' => $message->user_id,
                'role' => MessageRole::Assistant,
                'content' => $reply->text,
                'status' => MessageStatus::Completed,
                'reply_to_id' => $message->id,
                'input_tokens' => $reply->inputTokens,
                'output_tokens' => $reply->outputTokens,
            ]);

            $message->update([
                'status' => MessageStatus::Completed,
                'input_tokens' => $reply->inputTokens,
                'output_tokens' => $reply->outputTokens,
            ]);

            // Charge what the reply used (at most what was reserved) and refund the rest.
            $charged = min($reply->totalTokens(), $message->reserved_tokens);
            $wallet->credit($message->user, $message->reserved_tokens - $charged, LedgerEntryType::MessageRefund, "message:{$message->id}:refund");

            AgentResponded::dispatch($answer);
        });
    }

    /**
     * Out of attempts. Refund the tokens.
     */
    public function failed(?Throwable $exception): void
    {
        app(FailMessage::class)->handle($this->message->id, $exception?->getMessage() ?? 'Reply failed.');
    }
}
