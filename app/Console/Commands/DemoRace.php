<?php

namespace App\Console\Commands;

use App\Enums\LedgerEntryType;
use App\Models\User;
use App\Wallet\TokenWallet;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Starts many PHP processes that spend from the same wallet at the same moment.
 * With --unsafe they skip the lock and spend more than the wallet holds.
 */
#[Signature('demo:race {--workers=20} {--affordable=5} {--price=1000} {--unsafe : Use the naive read-then-write version}')]
#[Description('Race many processes against one wallet and check that nothing is overspent')]
class DemoRace extends Command
{
    public function handle(TokenWallet $wallet): int
    {
        $workers = (int) $this->option('workers');
        $affordable = (int) $this->option('affordable');
        $price = (int) $this->option('price');
        $unsafe = (bool) $this->option('unsafe');

        $user = User::query()->create(['name' => 'Race Demo', 'email' => 'race-'.Str::lower(Str::random(10)).'@demo.test', 'password' => Str::random(32)]);
        $user->wallet()->create();
        $startingBalance = $price * $affordable;
        $wallet->credit($user, $startingBalance, LedgerEntryType::Adjustment, "demo-race:{$user->id}:seed");

        // All workers start at this moment.
        $startAt = microtime(true) + 2.0;

        $this->line(sprintf('%s mode: %d workers, wallet holds %d (enough for %d at %d each)', $unsafe ? 'UNSAFE' : 'SAFE', $workers, $startingBalance, $affordable, $price));

        $results = Process::pool(function (Pool $pool) use ($workers, $user, $price, $startAt, $unsafe) {
            foreach (range(1, $workers) as $worker) {
                $command = [PHP_BINARY, 'artisan', 'demo:race-worker', (string) $user->id, (string) $worker, "--price={$price}", "--start-at={$startAt}"];

                if ($unsafe) {
                    $command[] = '--unsafe';
                }

                $pool->as("worker-{$worker}")->path(base_path())->env($this->childEnvironment())->timeout(60)->command($command);
            }
        })->start()->wait();

        $succeeded = collect($results)->filter(fn ($result) => trim($result->output()) === 'OK')->count();
        $errors = collect($results)->filter(fn ($result) => $result->failed());

        foreach ($errors as $name => $result) {
            $this->error("{$name}: ".trim($result->errorOutput()));
        }

        $balance = (int) DB::table('wallets')->where('user_id', $user->id)->value('balance');
        $ledgerSum = (int) DB::table('ledger_entries')->where('user_id', $user->id)->sum('amount');
        $spent = $startingBalance - $balance;
        $sold = $succeeded * $price;

        $this->table(['', 'value'], [
            ['purchases that succeeded', "{$succeeded} of {$workers}"],
            ['tokens actually removed from the balance', $spent],
            ['tokens that should have been removed', $sold],
            ['final balance', $balance],
            ['sum of ledger entries', $ledgerSum],
        ]);

        $correct = $errors->isEmpty() && $succeeded <= $affordable && $spent === $sold && $balance === $ledgerSum;

        if ($correct) {
            $this->info("{$succeeded} of {$workers} succeeded. Nothing was overspent and the balance matches the ledger.");

            return self::SUCCESS;
        }

        $this->error('Overspent: '.($sold - $spent).' tokens were handed out that the balance never paid for.');

        return self::FAILURE;
    }

    /**
     * Workers use the same database as this process.
     *
     * @return array<string, string>
     */
    private function childEnvironment(): array
    {
        $name = (string) config('database.default');
        $connection = (array) config("database.connections.{$name}");

        return [
            'APP_ENV' => (string) app()->environment(),
            'DB_CONNECTION' => $name,
            'DB_HOST' => (string) ($connection['host'] ?? ''),
            'DB_PORT' => (string) ($connection['port'] ?? ''),
            'DB_DATABASE' => (string) ($connection['database'] ?? ''),
            'DB_USERNAME' => (string) ($connection['username'] ?? ''),
            'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
        ];
    }
}
