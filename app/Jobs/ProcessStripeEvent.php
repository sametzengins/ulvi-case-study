<?php

namespace App\Jobs;

use App\Actions\CompleteTopUp;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Processes one stored webhook. If two copies run, the lock and processed_at stop the second.
 */
final class ProcessStripeEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $webhookEventId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 30, 120, 600];
    }

    public function handle(CompleteTopUp $completeTopUp): void
    {
        DB::transaction(function () use ($completeTopUp) {
            $event = WebhookEvent::query()->lockForUpdate()->find($this->webhookEventId);

            if ($event === null || $event->processed_at !== null) {
                return;
            }

            $session = $event->payload['data']['object'] ?? [];
            $paymentEvents = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];

            // Boleto and Pix complete before the money arrives, so wait until it is paid.
            if (in_array($event->type, $paymentEvents, true)
                && ($session['payment_status'] ?? null) === 'paid'
                && isset($session['metadata']['top_up_purchase_id'])) {
                $completeTopUp->handle((int) $session['metadata']['top_up_purchase_id']);
            }

            $event->update(['processed_at' => now()]);
        });
    }
}
