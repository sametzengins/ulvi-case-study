<?php

namespace App\Http\Controllers;

use App\Billing\StripeWebhookReceiver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeWebhookReceiver $receiver): JsonResponse
    {
        // The signature is over the raw body, so read it as it arrived.
        $duplicate = $receiver->receive($request->getContent(), (string) $request->header('Stripe-Signature'));

        return response()->json(['received' => true, 'duplicate' => $duplicate]);
    }
}
