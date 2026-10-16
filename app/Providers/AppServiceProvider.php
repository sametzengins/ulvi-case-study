<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // `composer dev` also runs the scheduler. Horizon runs the queue, so the default listener is not needed.
        DevCommands::artisan('schedule:work', 'scheduler');
        DevCommands::except('queue');

        // An N+1 query throws an error outside production.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
