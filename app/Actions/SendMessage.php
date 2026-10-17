<?php

namespace App\Actions;

use App\Conversations\TokenEstimator;
use App\Enums\LedgerEntryType;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Exceptions\ApiException;
use App\Jobs\GenerateReply;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Wallet\InsufficientTokens;
use App\Wallet\TokenWallet;
use Illuminate\Support\Facades\DB;

/**
 * Saves the message, reserves tokens for the reply and queues the job.
 * The reply arrives later over the WebSocket.
 */
final class SendMessage
{
    public function __construct(
        private readonly TokenWallet $wallet,
        private readonly TokenEstimator $estimator,
    ) {}

    /**
     * @return array{0: Message, 1: bool} the message, and whether this call created it
     */
    public function handle(User $user, Conversation $conversation, string $content, string $clientMessageId): array
    {
        return DB::transaction(function () use ($user, $conversation, $content, $clientMessageId) {
            // Requests from the same user wait for each other here, so a retried request
            // always finds the message the first one created.
            $this->wallet->lock($user);

            $existing = Message::query()
                ->where('user_id', $user->id)
                ->where('client_message_id', $clientMessageId)
                ->first();

            if ($existing !== null) {
                return [$existing, false];
            }

            $message = $conversation->messages()->create([
                'user_id' => $user->id,
                'role' => MessageRole::User,
                'content' => $content,
                'status' => MessageStatus::Queued,
                'client_message_id' => $clientMessageId,
            ]);
            $message->setRelation('conversation', $conversation);

            // Take the most the reply can cost. GenerateReply refunds what it did not use.
            $reserve = $this->estimator->estimate($message);

            try {
                $this->wallet->debit($user, $reserve, LedgerEntryType::MessageHold, "message:{$message->id}:hold");
            } catch (InsufficientTokens $e) {
                throw new ApiException('quota_exhausted', 402, ['needed' => $e->needed, 'available' => $e->available]);
            }

            $message->update(['reserved_tokens' => $reserve]);

            // Queued only if the transaction commits.
            GenerateReply::dispatch($message)->afterCommit();

            return [$message, true];
        });
    }
}
