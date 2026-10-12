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

    /*
    | OpenAI powers the admin editor's Generate / enhance button. The model
    | name is read only from here; the key never leaves this config.
    */

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL'),
        'url' => 'https://api.openai.com/v1/chat/completions',
        'timeout' => (int) env('OPENAI_TIMEOUT', 30),
        'max_completion_tokens' => (int) env('OPENAI_MAX_COMPLETION_TOKENS', 2000),
        'requests_per_minute' => (int) env('OPENAI_REQUESTS_PER_MINUTE', 5),
        'duplicate_wait' => 35,
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
