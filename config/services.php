<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1'),
    ],

    'grok' => [
        'api_key' => env('GROK_API_KEY'),
        'base_url' => env('GROK_API_URL', 'https://api.x.ai/v1'),
    ],

    'recaptcha' => [
        'secret' => env('RECAPTCHA_SECRET_KEY'),
    ],

    'ffmpeg' => [
        'path' => env('FFMPEG_PATH', 'ffmpeg'),
    ],

    'n8n' => [
        'telegram_webhook' => env('N8N_TELEGRAM_WEBHOOK_URL'),
        'webhook_secret'   => env('N8N_WEBHOOK_SECRET'),
        'bot_username'     => env('TELEGRAM_BOT_USERNAME'),
    ],

];
