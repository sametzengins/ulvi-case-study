<?php

use Illuminate\Support\Facades\Schedule;

// Refund messages whose reply job was lost.
Schedule::command('messages:fail-stale')->everyMinute()->withoutOverlapping();

// Check every balance against the ledger.
Schedule::command('ledger:verify')->dailyAt('03:00');
