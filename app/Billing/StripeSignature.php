<?php

namespace App\Billing;

/**
 * Signs a payload the way Stripe does, so the fake checkout and the tests go through real verification.
 */
final class StripeSignature
{
    public static function header(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return "t={$timestamp},v1={$signature}";
    }
}
