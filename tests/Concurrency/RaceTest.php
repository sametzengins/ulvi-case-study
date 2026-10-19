<?php

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;

/*
| These tests start real PHP processes that hit the same PostgreSQL rows at the same moment.
| A test that runs everything in one process and one transaction cannot see a race;
| these can. Data must be committed for the workers to see it, hence truncation, not transactions.
*/

uses(DatabaseTruncation::class);

it('never overspends when 20 processes spend from one wallet at the same instant', function () {
    $this->artisan('demo:race', ['--workers' => 20, '--affordable' => 5, '--price' => 1000])
        ->expectsOutputToContain('5 of 20 succeeded')
        ->assertSuccessful();

    expect(Artisan::call('ledger:verify'))->toBe(0);
});

it('catches the naive read-then-write version overspending', function () {
    // Proof that the test above would notice a broken implementation.
    $this->artisan('demo:race', ['--workers' => 12, '--affordable' => 2, '--price' => 1000, '--unsafe' => true])
        ->expectsOutputToContain('Overspent')
        ->assertFailed();
});
