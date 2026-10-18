<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\StartTopUpCheckout;
use App\Billing\TopUpPack;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TopUpController extends Controller
{
    public function index(): JsonResponse
    {
        $currency = (string) config('ulvi.currency');

        return response()->json([
            'data' => array_map(fn (TopUpPack $pack) => [
                'key' => $pack->key,
                'name' => $pack->name,
                'tokens' => $pack->tokens,
                'price' => ['amount' => $pack->price, 'currency' => $currency],
            ], TopUpPack::all()),
        ]);
    }

    public function checkout(Request $request, StartTopUpCheckout $startCheckout): JsonResponse
    {
        $validated = $request->validate([
            'pack' => ['required', Rule::in(TopUpPack::keys())],
        ]);

        [$purchase, $session] = $startCheckout->handle($request->user(), TopUpPack::find($validated['pack']));

        return response()->json([
            'data' => [
                'purchase_id' => $purchase->id,
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ],
        ], 201);
    }
}
