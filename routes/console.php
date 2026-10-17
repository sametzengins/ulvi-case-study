<?php

use Illuminate\Support\Facades\Schedule;

// Check every balance against the ledger.
Schedule::command('ledger:verify')->dailyAt('03:00');
