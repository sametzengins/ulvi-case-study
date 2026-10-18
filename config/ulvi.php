<?php

return [

    // Tokens every new account starts with.
    'signup_tokens' => 100_000,

    /*
    |--------------------------------------------------------------------------
    | Top-up packs
    |--------------------------------------------------------------------------
    |
    | Prices are in kuruş and mirror what the live app showed.
    |
    */

    'currency' => 'try',

    'top_up_packs' => [
        'boost_s' => ['name' => 'Boost S', 'tokens' => 3_750_000, 'price' => 4_900],
        'boost_m' => ['name' => 'Boost M', 'tokens' => 10_350_000, 'price' => 14_900],
        'boost_l' => ['name' => 'Boost L', 'tokens' => 23_500_000, 'price' => 29_900],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversations
    |--------------------------------------------------------------------------
    */

    'messages' => [
        // The longest reply. Every message reserves this plus its input.
        'max_output_tokens' => (int) env('REPLY_MAX_OUTPUT_TOKENS', 4096),

        // How many earlier messages are sent along with a new one.
        'history_limit' => 20,

        // messages:fail-stale fails messages that waited longer than this.
        'stale_after_minutes' => 15,

        'reply_driver' => env('REPLY_DRIVER', 'fake'),

        'fake' => [
            'latency_ms' => (int) env('FAKE_REPLY_LATENCY_MS', 1500),
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
            'timeout' => 45.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | "fake" runs the whole purchase flow locally: checkout pages are served
    | by this app and webhooks are signed with the same secret Stripe would use.
    |
    */

    'payment_gateway' => env('PAYMENT_GATEWAY', 'fake'),

    // The /demo pages. Off in production.
    'demo_enabled' => (bool) env('DEMO_ENABLED', env('APP_ENV') !== 'production'),

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', 'whsec_local_development_only'),
        'webhook_tolerance' => 300,
    ],

];
