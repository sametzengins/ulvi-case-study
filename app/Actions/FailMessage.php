<?php

namespace App\Actions;

use App\Enums\LedgerEntryType;
use App\Enums\MessageStatus;
use App\Events\AgentFailed;
use App\Models\Message;
use App\Wallet\TokenWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Marks the message failed and refunds the tokens it reserved.
 * Safe to call more than once. Only the first call changes anything.
 */
final class FailMessage
{
    public function __construct(private readonly TokenWallet $wallet) {}

    public function handle(int $messageId, string $reason): void
    {
        DB::transaction(function () use ($messageId, $reason) {
            $message = Message::query()->with('user')->lockForUpdate()->find($messageId);

            if ($message === null || $message->status->isFinal()) {
                return;
            }

            $message->update([
                'status' => MessageStatus::Failed,
                'failure_reason' => Str::limit($reason, 250),
            ]);

            // Same key as the refund in GenerateReply, so a message is refunded only once.
            $this->wallet->credit($message->user, $message->reserved_tokens, LedgerEntryType::MessageRefund, "message:{$message->id}:refund");

            AgentFailed::dispatch($message);
        });
    }
}
