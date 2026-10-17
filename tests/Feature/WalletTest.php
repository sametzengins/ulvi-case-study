<?php

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Wallet\InsufficientTokens;
use App\Wallet\TokenWallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->wallet = app(TokenWallet::class);
});

it('records every movement with the balance before and after it, and the balance matches the ledger', function () {
    $user = userWithTokens(1_000);

    $this->wallet->debit($user, 300, LedgerEntryType::MessageHold, 'test:hold');
    $this->wallet->credit($user, 200, LedgerEntryType::MessageRefund, 'test:refund');

    $entries = LedgerEntry::query()->orderBy('id')->get();

    expect($entries->map(fn (LedgerEntry $e) => [$e->balance_before, $e->amount, $e->balance_after])->all())
        ->toBe([[0, 1_000, 1_000], [1_000, -300, 700], [700, 200, 900]])
        ->and(balance($user))->toBe(900)
        ->and(Artisan::call('ledger:verify'))->toBe(0);
});

it('refuses to go below zero and writes nothing', function () {
    $user = userWithTokens(100);
    $entriesBefore = LedgerEntry::query()->count();

    expect(fn () => $this->wallet->debit($user, 101, LedgerEntryType::MessageHold, 'test:too-much'))
        ->toThrow(InsufficientTokens::class);

    expect(LedgerEntry::query()->count())->toBe($entriesBefore)
        ->and(balance($user))->toBe(100);
});

it('applies an idempotency key once', function () {
    $user = userWithTokens(0);

    $first = $this->wallet->credit($user, 500, LedgerEntryType::TopUp, 'top-up:42');
    $again = $this->wallet->credit($user, 500, LedgerEntryType::TopUp, 'top-up:42');

    expect($first)->not->toBeNull()
        ->and($again)->toBeNull()
        ->and(balance($user))->toBe(500);
});

it('is protected by the database, not only by the code', function () {
    $user = userWithTokens(100);
    $entry = LedgerEntry::query()->firstOrFail();

    // Each attempt runs in a savepoint so a refused statement does not break the test transaction.
    $attempt = fn (Closure $statement) => fn () => DB::transaction($statement);

    // A negative balance is refused by a CHECK constraint.
    expect($attempt(fn () => DB::table('wallets')->where('user_id', $user->id)->update(['balance' => -1])))
        ->toThrow(QueryException::class, 'wallets_balance_not_negative');

    // Ledger rows cannot be edited or deleted, even with raw SQL.
    expect($attempt(fn () => DB::table('ledger_entries')->where('id', $entry->id)->update(['amount' => 1])))
        ->toThrow(QueryException::class, 'append-only');
    expect($attempt(fn () => DB::table('ledger_entries')->where('id', $entry->id)->delete()))
        ->toThrow(QueryException::class, 'append-only');
});

it('notices when a balance no longer matches its ledger', function () {
    $user = userWithTokens(100);

    // A write that skipped the ledger.
    DB::table('wallets')->where('user_id', $user->id)->update(['balance' => 5_000]);

    expect(Artisan::call('ledger:verify'))->toBe(1);
});
