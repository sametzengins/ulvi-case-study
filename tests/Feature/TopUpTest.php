<?php

use App\Enums\TopUpStatus;
use App\Models\TopUpPurchase;

it('records the purchase before sending the user to checkout', function () {
    $user = userWithTokens();

    $response = $this->actingAs($user)
        ->postJson('/api/v1/billing/top-ups/checkout', ['pack' => 'boost_s'])
        ->assertCreated();

    $purchase = TopUpPurchase::query()->sole();

    expect($purchase->status)->toBe(TopUpStatus::Pending)
        ->and($purchase->amount)->toBe(4_900)
        ->and($purchase->currency)->toBe('try')
        ->and($purchase->checkout_session_id)->toBe($response->json('data.session_id'))
        ->and($response->json('data.checkout_url'))->toContain('/demo/checkout/');

    // Nothing is credited until the payment webhook says so.
    expect(balance($user))->toBe(100_000);
});

it('lists the packs with their prices in kuruş', function () {
    $this->actingAs(userWithTokens())->getJson('/api/v1/billing/top-ups')
        ->assertOk()
        ->assertJsonPath('data.0.key', 'boost_s')
        ->assertJsonPath('data.0.price', ['amount' => 4_900, 'currency' => 'try']);
});

it('rejects a pack that does not exist', function () {
    $this->actingAs(userWithTokens())
        ->postJson('/api/v1/billing/top-ups/checkout', ['pack' => 'boost_xxl'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed');

    expect(TopUpPurchase::query()->count())->toBe(0);
});
