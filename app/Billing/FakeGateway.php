<?php

namespace App\Billing;

use App\Models\TopUpPurchase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Stands in for Stripe locally. The webhook code it feeds is the real one.
 */
final class FakeGateway implements PaymentGateway
{
    public function createTopUpCheckout(TopUpPurchase $purchase, TopUpPack $pack): CheckoutSession
    {
        $session = [
            'id' => 'cs_fake_'.Str::random(24),
            'object' => 'checkout.session',
            'mode' => 'payment',
            'client_reference_id' => (string) $purchase->user_id,
            'payment_status' => 'unpaid',
            'amount_total' => $purchase->amount,
            'currency' => $purchase->currency,
            'metadata' => ['top_up_purchase_id' => (string) $purchase->id],
            'description' => "{$pack->name} · ".number_format($pack->tokens).' tokens',
        ];

        $this->putSession($session);

        return new CheckoutSession($session['id'], route('demo.checkout.show', $session['id']));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function session(string $id): ?array
    {
        return Cache::get("fake-stripe:session:{$id}");
    }

    /**
     * @param  array<string, mixed>  $session
     */
    public function putSession(array $session): void
    {
        Cache::put("fake-stripe:session:{$session['id']}", $session, now()->addDay());
    }
}
