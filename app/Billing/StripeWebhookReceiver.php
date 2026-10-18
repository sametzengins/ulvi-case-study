<?php

namespace App\Billing;

use App\Exceptions\ApiException;
use App\Jobs\ProcessStripeEvent;
use App\Models\WebhookEvent;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;

/**
 * Verifies the webhook, stores it and queues it. The money moves in the job, not here.
 */
final class StripeWebhookReceiver
{
    /**
     * @return bool whether this event had already been received
     */
    public function receive(string $payload, string $signatureHeader): bool
    {
        try {
            // Checks the signature and that the request is not older than the tolerance.
            $event = Webhook::constructEvent(
                $payload,
                $signatureHeader,
                (string) config('ulvi.stripe.webhook_secret'),
                (int) config('ulvi.stripe.webhook_tolerance'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            throw new ApiException('invalid_signature', 400);
        }

        // The unique event_id makes a second delivery insert nothing.
        $inserted = WebhookEvent::query()->insertOrIgnore([
            'event_id' => $event->id,
            'type' => $event->type,
            'payload' => $payload,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = WebhookEvent::query()->where('event_id', $event->id)->firstOrFail();

        // Queue it on every delivery until it is processed, in case the first job was lost.
        if ($record->processed_at === null) {
            ProcessStripeEvent::dispatch($record->id);
        }

        return $inserted === 0;
    }
}
