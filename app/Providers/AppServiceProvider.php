<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Conversations\AnthropicReplyGenerator;
use App\Conversations\FakeReplyGenerator;
use App\Conversations\ReplyGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ReplyGenerator::class, function () {
            if (config('ulvi.messages.reply_driver') !== 'anthropic') {
                return new FakeReplyGenerator((int) config('ulvi.messages.fake.latency_ms'));
            }

            $anthropic = (array) config('ulvi.messages.anthropic');

            return new AnthropicReplyGenerator(
                // The queue retries, so the SDK does not.
                new AnthropicClient(
                    apiKey: $anthropic['api_key'],
                    requestOptions: ['timeout' => (float) $anthropic['timeout'], 'maxRetries' => 0],
                ),
                (string) $anthropic['model'],
                (int) config('ulvi.messages.max_output_tokens'),
            );
        });
    }

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
