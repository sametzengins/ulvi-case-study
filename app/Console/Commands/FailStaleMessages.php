<?php

namespace App\Console\Commands;

use App\Actions\FailMessage;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * If a reply job is lost, its message would keep its tokens forever. This fails those messages.
 */
#[Signature('messages:fail-stale')]
#[Description('Fail messages stuck in queued/processing and refund the tokens they reserved')]
class FailStaleMessages extends Command
{
    public function handle(FailMessage $failMessage): int
    {
        $cutoff = now()->subMinutes((int) config('ulvi.messages.stale_after_minutes'));
        $failed = 0;

        Message::query()
            ->where('role', MessageRole::User)
            ->whereIn('status', MessageStatus::pendingValues())
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($messages) use ($failMessage, &$failed) {
                foreach ($messages as $message) {
                    $failMessage->handle($message->id, 'Timed out waiting for a reply.');
                    $failed++;
                }
            });

        $this->info("Failed {$failed} stale message(s).");

        return self::SUCCESS;
    }
}
