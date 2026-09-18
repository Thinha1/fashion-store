<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'vietqr' => [
        'business_cache_seconds' => (int) env('VIETQR_BUSINESS_CACHE_SECONDS', 900),
        'business_requests_per_minute' => (int) env('VIETQR_BUSINESS_REQUESTS_PER_MINUTE', 10),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'anthropic'),
        'requests_per_minute' => (int) env('AI_REQUESTS_PER_MINUTE', 6),
        'max_image_kb' => (int) env('AI_MAX_IMAGE_KB', 4096),
        'max_history_messages' => (int) env('AI_MAX_HISTORY_MESSAGES', 24),
        // Reasoning models (e.g. Qwen-thinking style) spend a large share of
        // this budget on hidden reasoning before emitting the actual JSON
        // reply, so the default is well above a typical non-reasoning model.
        'max_tokens' => (int) env('AI_MAX_TOKENS', 3072),
        'anthropic' => [
            'key' => env('ANTHROPIC_API_KEY'),
            'model' => env('AI_MODEL', 'claude-sonnet-5'),
            'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        ],
        'openai_compatible' => [
            'endpoint' => env('AI_SELF_HOST_ENDPOINT'),
            'key' => env('AI_SELF_HOST_KEY'),
            'model' => env('AI_SELF_HOST_MODEL'),
        ],
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
