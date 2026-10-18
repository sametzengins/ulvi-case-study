<?php

use App\Billing\StripeSignature;
use App\Billing\TopUpPack;
use App\Enums\LedgerEntryType;
use App\Enums\TopUpStatus;
use App\Jobs\ProcessStripeEvent;
use App\Models\LedgerEntry;
use App\Models\TopUpPurchase;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Queue;

/**
 * A pending top-up, as StartTopUpCheckout leaves it, and the checkout session Stripe would report.
 *
 * @return array{0: TopUpPurchase, 1: array<string, mixed>}
 */
function pendingTopUp(User $user, string $paymentStatus = 'paid'): array
{
    $pack = TopUpPack::find('boost_s');
    $purchase = TopUpPurchase::query()->create([
        'user_id' => $user->id,
        'pack' => $pack->key,
        'tokens' => $pack->tokens,
        'amount' => $pack->price,
        'currency' => 'try',
        'checkout_session_id' => 'cs_test_'.$user->id,
        'status' => TopUpStatus::Pending,
    ]);

    $session = [
        'id' => $purchase->checkout_session_id,
        'object' => 'checkout.session',
        'mode' => 'payment',
        'payment_status' => $paymentStatus,
        'metadata' => ['top_up_purchase_id' => (string) $purchase->id],
    ];

    return [$purchase, $session];
}

function topUpCredits(): int
{
    return LedgerEntry::query()->where('type', LedgerEntryType::TopUp)->count();
}

describe('verification', function () {
    it('rejects a request that was not signed with our secret', function () {
        deliverStripeEvent(stripeEvent('checkout.session.completed', []), secret: 'whsec_someone_else')
            ->assertStatus(400)
            ->assertJsonPath('code', 'invalid_signature');

        expect(WebhookEvent::query()->count())->toBe(0);
    });

    it('rejects a correctly signed request that is outside the timing window', function () {
        deliverStripeEvent(stripeEvent('checkout.session.completed', []), timestamp: time() - 301)
            ->assertStatus(400)
            ->assertJsonPath('code', 'invalid_signature');
    });

    it('rejects a body that was changed after signing', function () {
        $payload = json_encode(stripeEvent('checkout.session.completed', ['amount_total' => 100]));
        $signature = StripeSignature::header($payload, (string) config('ulvi.stripe.webhook_secret'));
        $tampered = str_replace('"amount_total":100', '"amount_total":1', $payload);

        $this->call('POST', '/api/webhooks/stripe', server: ['HTTP_STRIPE_SIGNATURE' => $signature, 'HTTP_ACCEPT' => 'application/json'], content: $tampered)
            ->assertStatus(400);
    });
});

describe('top-ups', function () {
    it('credits a top-up once when the same event is delivered three times', function () {
        $user = userWithTokens(0);
        [$purchase, $session] = pendingTopUp($user);
        $event = stripeEvent('checkout.session.completed', $session);

        deliverStripeEvent($event)->assertOk()->assertJson(['received' => true, 'duplicate' => false]);
        deliverStripeEvent($event)->assertOk()->assertJson(['received' => true, 'duplicate' => true]);
        deliverStripeEvent($event)->assertOk()->assertJson(['received' => true, 'duplicate' => true]);

        expect(WebhookEvent::query()->count())->toBe(1)
            ->and(topUpCredits())->toBe(1)
            ->and(balance($user))->toBe($purchase->tokens)
            ->and($purchase->fresh()->status)->toBe(TopUpStatus::Completed);
    });

    it('credits once when two different events report the same payment', function () {
        $user = userWithTokens(0);
        [$purchase, $session] = pendingTopUp($user);

        deliverStripeEvent(stripeEvent('checkout.session.completed', $session))->assertOk();
        deliverStripeEvent(stripeEvent('checkout.session.async_payment_succeeded', $session))->assertOk();

        expect(WebhookEvent::query()->count())->toBe(2)
            ->and(topUpCredits())->toBe(1)
            ->and(balance($user))->toBe($purchase->tokens);
    });

    it('waits for the money when a boleto checkout completes unpaid', function () {
        $user = userWithTokens(0);
        [$purchase, $session] = pendingTopUp($user, paymentStatus: 'unpaid');

        deliverStripeEvent(stripeEvent('checkout.session.completed', $session))->assertOk();
        expect(topUpCredits())->toBe(0);

        deliverStripeEvent(stripeEvent('checkout.session.async_payment_succeeded', [...$session, 'payment_status' => 'paid']))->assertOk();
        expect(topUpCredits())->toBe(1)
            ->and($purchase->fresh()->status)->toBe(TopUpStatus::Completed);
    });

    it('still processes an event whose first delivery found the queue down', function () {
        $user = userWithTokens(0);
        [, $session] = pendingTopUp($user);
        $event = stripeEvent('checkout.session.completed', $session);

        Queue::fake();
        deliverStripeEvent($event)->assertOk();   // stored, but the job never runs
        deliverStripeEvent($event)->assertOk();   // Stripe retries: not processed yet, so it is queued again

        Queue::assertPushed(ProcessStripeEvent::class, 2);

        // Both copies of the job run. The money moves once.
        Queue::pushed(ProcessStripeEvent::class)->each(fn (ProcessStripeEvent $job) => app()->call([$job, 'handle']));

        expect(topUpCredits())->toBe(1)
            ->and(WebhookEvent::query()->sole()->processed_at)->not->toBeNull();
    });
});
