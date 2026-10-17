<?php

use App\Enums\LedgerEntryType;
use App\Models\User;
use App\Wallet\TokenWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A user whose wallet holds exactly this many tokens.
 */
function userWithTokens(int $tokens = 100_000): User
{
    $user = User::factory()->create();

    app(TokenWallet::class)->credit($user, $tokens, LedgerEntryType::Adjustment, "test:seed:{$user->id}");

    return $user;
}

function balance(User $user): int
{
    return (int) $user->wallet()->value('balance');
}
