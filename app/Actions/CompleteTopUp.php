<?php

namespace App\Actions;

use App\Enums\LedgerEntryType;
use App\Enums\TopUpStatus;
use App\Models\TopUpPurchase;
use App\Models\User;
use App\Wallet\TokenWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Credits a paid top-up. Two different Stripe events can report the same payment,
 * so the check is on the purchase, not on the event.
 */
final class CompleteTopUp
{
    public function __construct(private readonly TokenWallet $wallet) {}

    public function handle(int $purchaseId): void
    {
        DB::transaction(function () use ($purchaseId) {
            $purchase = TopUpPurchase::query()->lockForUpdate()->find($purchaseId);

            if ($purchase === null) {
                Log::warning('Paid checkout for an unknown top-up purchase.', ['purchase_id' => $purchaseId]);

                return;
            }

            if ($purchase->status === TopUpStatus::Completed) {
                return;
            }

            $this->wallet->credit(
                User::query()->findOrFail($purchase->user_id),
                $purchase->tokens,
                LedgerEntryType::TopUp,
                "top-up:{$purchase->id}",
            );

            $purchase->update(['status' => TopUpStatus::Completed, 'completed_at' => now()]);
        });
    }
}
