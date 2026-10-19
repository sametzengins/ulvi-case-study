<?php

namespace App\Console\Commands;

use App\Enums\LedgerEntryType;
use App\Models\User;
use App\Wallet\InsufficientTokens;
use App\Wallet\TokenWallet;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Hidden;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('demo:race-worker {user} {worker} {--price=1000} {--start-at=0} {--unsafe}')]
#[Description('One contender in demo:race')]
#[Hidden]
class DemoRaceWorker extends Command
{
    public function handle(TokenWallet $wallet): int
    {
        $user = User::query()->findOrFail((int) $this->argument('user'));
        $price = (int) $this->option('price');

        while (microtime(true) < (float) $this->option('start-at')) {
            usleep(500);
        }

        $succeeded = $this->option('unsafe')
            ? $this->spendUnsafely($user, $price)
            : $this->spendSafely($wallet, $user, $price);

        $this->line($succeeded ? 'OK' : 'DECLINED');

        return self::SUCCESS;
    }

    private function spendSafely(TokenWallet $wallet, User $user, int $price): bool
    {
        try {
            $wallet->debit($user, $price, LedgerEntryType::Adjustment, "demo-race:{$user->id}:worker-{$this->argument('worker')}");

            return true;
        } catch (InsufficientTokens) {
            return false;
        }
    }

    /**
     * Read, wait, write. Looks fine with one user.
     */
    private function spendUnsafely(User $user, int $price): bool
    {
        $wallet = DB::table('wallets')->where('user_id', $user->id)->first();

        // A real request has a gap here too. This makes it long enough to see.
        usleep(random_int(20_000, 80_000));

        if ($wallet->balance < $price) {
            return false;
        }

        DB::table('wallets')->where('id', $wallet->id)->update(['balance' => $wallet->balance - $price]);

        return true;
    }
}
