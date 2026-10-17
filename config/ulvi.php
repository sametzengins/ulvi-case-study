<?php

return [

    // Tokens every new account starts with.
    'signup_tokens' => 100_000,

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

];
