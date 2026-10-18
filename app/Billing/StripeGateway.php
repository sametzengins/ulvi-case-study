<?php

namespace App\Billing;

use App\Models\TopUpPurchase;
use Stripe\StripeClient;

final class StripeGateway implements PaymentGateway
{
    public function __construct(private readonly StripeClient $stripe) {}

    public function createTopUpCheckout(TopUpPurchase $purchase, TopUpPack $pack): CheckoutSession
    {
        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'client_reference_id' => (string) $purchase->user_id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $purchase->currency,
                    'unit_amount' => $purchase->amount,
                    'product_data' => ['name' => "{$pack->name} · ".number_format($pack->tokens).' tokens'],
                ],
            ]],
            'metadata' => ['top_up_purchase_id' => (string) $purchase->id],
            'success_url' => url('/demo?checkout=success'),
            'cancel_url' => url('/demo?checkout=cancelled'),
        ], ['idempotency_key' => "top-up-checkout-{$purchase->id}"]);

        return new CheckoutSession($session->id, (string) $session->url);
    }
}
