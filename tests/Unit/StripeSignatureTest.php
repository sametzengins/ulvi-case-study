<?php

use App\Billing\StripeSignature;
use App\Conversations\TokenEstimator;
use Stripe\WebhookSignature;

it('signs exactly the way Stripe verifies', function () {
    $payload = '{"id":"evt_1","object":"event"}';
    $header = StripeSignature::header($payload, 'whsec_secret', 1_800_000_000);

    expect($header)->toStartWith('t=1800000000,v1=')
        ->and(WebhookSignature::verifyHeader($payload, $header, 'whsec_secret', tolerance: null))->toBeTrue();
});

it('rounds token estimates up', function (int $characters, int $tokens) {
    expect(TokenEstimator::tokensFor($characters))->toBe($tokens);
})->with([
    [0, 0],
    [1, 1],
    [4, 1],
    [5, 2],
    [4_000, 1_000],
]);
