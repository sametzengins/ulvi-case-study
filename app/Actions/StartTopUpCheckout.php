<?php

namespace App\Actions;

use App\Billing\CheckoutSession;
use App\Billing\PaymentGateway;
use App\Billing\TopUpPack;
use App\Enums\TopUpStatus;
use App\Models\TopUpPurchase;
use App\Models\User;

final class StartTopUpCheckout
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * @return array{0: TopUpPurchase, 1: CheckoutSession}
     */
    public function handle(User $user, TopUpPack $pack): array
    {
        // Saved before Stripe knows about it. The webhook finds it by the id in the metadata.
        $purchase = TopUpPurchase::query()->create([
            'user_id' => $user->id,
            'pack' => $pack->key,
            'tokens' => $pack->tokens,
            'amount' => $pack->price,
            'currency' => (string) config('ulvi.currency'),
            'status' => TopUpStatus::Pending,
        ]);

        $session = $this->gateway->createTopUpCheckout($purchase, $pack);

        $purchase->update(['checkout_session_id' => $session->id]);

        return [$purchase, $session];
    }
}
