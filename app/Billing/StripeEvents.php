<?php

namespace App\Billing;

use Illuminate\Support\Str;

/**
 * Builds events in the shape Stripe sends them.
 */
final class StripeEvents
{
    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    public static function make(string $type, array $object, ?string $id = null): array
    {
        return [
            'id' => $id ?? 'evt_fake_'.Str::random(24),
            'object' => 'event',
            'api_version' => '2026-09-30',
            'created' => time(),
            'livemode' => false,
            'type' => $type,
            'data' => ['object' => $object],
        ];
    }
}
