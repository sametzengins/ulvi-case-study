<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Checks that every balance equals the sum of its ledger entries,
 * and that every entry starts where the previous one ended.
 */
#[Signature('ledger:verify')]
#[Description('Check every wallet balance against its ledger')]
class VerifyLedger extends Command
{
    public function handle(): int
    {
        $mismatches = DB::select(<<<'SQL'
            SELECT w.user_id, w.balance, COALESCE(SUM(e.amount), 0) AS ledger_sum
            FROM wallets w
            LEFT JOIN ledger_entries e ON e.user_id = w.user_id
            GROUP BY w.id
            HAVING w.balance <> COALESCE(SUM(e.amount), 0)
            SQL);

        $brokenChains = DB::select(<<<'SQL'
            SELECT id, user_id, balance_before, previous_after
            FROM (
                SELECT id, user_id, balance_before,
                       LAG(balance_after, 1, 0) OVER (PARTITION BY user_id ORDER BY id) AS previous_after
                FROM ledger_entries
            ) chained
            WHERE balance_before <> previous_after
            SQL);

        foreach ($mismatches as $row) {
            $this->error("User {$row->user_id}: balance {$row->balance}, ledger says {$row->ledger_sum}");
        }

        foreach ($brokenChains as $row) {
            $this->error("Entry {$row->id} (user {$row->user_id}) starts at {$row->balance_before}, previous entry ended at {$row->previous_after}");
        }

        if ($mismatches === [] && $brokenChains === []) {
            $wallets = DB::table('wallets')->count();
            $entries = DB::table('ledger_entries')->count();
            $this->info("OK: {$wallets} wallets, {$entries} entries, every balance matches its ledger.");

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
