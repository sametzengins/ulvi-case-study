<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by((string) $request->user()?->id ?: $request->ip()));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute(20)->by((string) $request->user()?->id));
    }
}
