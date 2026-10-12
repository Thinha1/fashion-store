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

    // SePay calls POST /webhooks/sepay with "Authorization: Apikey <key>";
    // with no key configured every webhook call is refused.
    'sepay' => [
        'webhook_api_key' => env('SEPAY_WEBHOOK_API_KEY'),
    ],

    // Provinces/wards for address forms (provinces.open-api.vn, v2 = the
    // 2025 two-level structure). Empty URL = free-text address fields.
    'vn_divisions' => [
        'base_url' => env('VN_DIVISIONS_API_URL', 'https://provinces.open-api.vn/api/v2'),
        'cache_days' => (int) env('VN_DIVISIONS_CACHE_DAYS', 30),
    ],

    'vietqr' => [
        'business_cache_seconds' => (int) env('VIETQR_BUSINESS_CACHE_SECONDS', 900),
        'business_requests_per_minute' => (int) env('VIETQR_BUSINESS_REQUESTS_PER_MINUTE', 10),
    ],

    'ai' => [
        'requests_per_minute' => (int) env('AI_REQUESTS_PER_MINUTE', 6),
        'max_image_kb' => (int) env('AI_MAX_IMAGE_KB', 4096),
        'max_history_messages' => (int) env('AI_MAX_HISTORY_MESSAGES', 24),
        // Customer-facing shopping-assist widget (guest-callable, public
        // traffic) — a separate, higher budget than the admin assistant
        // above, which only a handful of trusted staff ever call.
        'shopping_assist_requests_per_minute' => (int) env('AI_SHOPPING_REQUESTS_PER_MINUTE', 15),
        'shopping_assist_max_history_messages' => (int) env('AI_SHOPPING_MAX_HISTORY_MESSAGES', 20),
        // Reasoning models (e.g. Qwen-thinking style) spend a large share of
        // this budget on hidden reasoning before emitting the actual JSON
        // reply, so the default is well above a typical non-reasoning model.
        'max_tokens' => (int) env('AI_MAX_TOKENS', 3072),
        // Self-hosted, OpenAI-compatible chat completions endpoint — paste
        // the full base URL exactly as given (usually already ends in
        // "/v1"); OpenAiCompatibleProvider only appends "/chat/completions".
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
