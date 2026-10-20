<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CharacterController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\TopUpController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// Every route here is in openapi.yaml. ContractTest fails when the two differ.

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
    });

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);

        Route::get('characters', [CharacterController::class, 'index']);

        Route::post('conversations', [ConversationController::class, 'store']);
        Route::get('conversations/{conversation}/messages', [MessageController::class, 'index'])->whereNumber('conversation');
        Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])
            ->whereNumber('conversation')
            ->middleware('throttle:messages');

        Route::get('billing/wallet', [WalletController::class, 'show']);
        Route::get('billing/ledger', [WalletController::class, 'ledger']);
        Route::get('billing/top-ups', [TopUpController::class, 'index']);
        Route::post('billing/top-ups/checkout', [TopUpController::class, 'checkout']);
    });
});

// Stripe proves who it is with the signature, not a token.
Route::post('webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');
