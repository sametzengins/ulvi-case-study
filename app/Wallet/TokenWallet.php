<?php

namespace App\Wallet;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The only code that changes a balance. Every change locks the wallet row, writes a ledger
 * entry with the balance before and after, and has a key that can only be used once.
 */
final class TokenWallet
{
    /**
     * Locks the wallet row until the transaction ends. Other requests for this user wait here.
     */
    public function lock(User $user): Wallet
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('TokenWallet::lock() must run inside a database transaction.');
        }

        return Wallet::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Returns null if the key was already used.
     */
    public function credit(User $user, int $amount, LedgerEntryType $type, string $idempotencyKey): ?LedgerEntry
    {
        return $this->post($user, $amount, $type, $idempotencyKey);
    }

    /**
     * Returns null if the key was already used.
     *
     * @throws InsufficientTokens
     */
    public function debit(User $user, int $amount, LedgerEntryType $type, string $idempotencyKey): ?LedgerEntry
    {
        return $this->post($user, -$amount, $type, $idempotencyKey);
    }

    /**
     * @throws InsufficientTokens
     */
    private function post(User $user, int $amount, LedgerEntryType $type, string $idempotencyKey): ?LedgerEntry
    {
        return DB::transaction(function () use ($user, $amount, $type, $idempotencyKey) {
            $wallet = $this->lock($user);

            // We hold this user's lock, so this check cannot race. The unique index is a backstop.
            if ($amount === 0 || LedgerEntry::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                return null;
            }

            $before = $wallet->balance;
            $after = $before + $amount;

            if ($after < 0) {
                throw new InsufficientTokens(-$amount, $before);
            }

            $entry = LedgerEntry::query()->create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);

            $wallet->update(['balance' => $after]);

            return $entry;
        });
    }
}
