<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file stores credentials and metadata for third-party integrations
    | used by Safe Handover. All secrets should be pulled from the environment
    | and never hard-coded within the repository.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
        'connect' => [
            'client_id' => env('STRIPE_CLIENT_ID'),
            'webhook_secret' => env('STRIPE_CONNECT_WEBHOOK_SECRET'),
            'account_country' => env('STRIPE_ACCOUNT_COUNTRY', 'US'),
        ],
        'statement_descriptor' => env('STRIPE_STATEMENT_DESCRIPTOR', 'SAFE HANDOVER'),
    ],

    'stripe_identity' => [
        'key' => env('STRIPE_IDENTITY_KEY'),
        'refresh_url' => env('STRIPE_IDENTITY_REFRESH_URL'),
        'return_url' => env('STRIPE_IDENTITY_RETURN_URL'),
    ],

    'pusher' => [
        'app_id' => env('PUSHER_APP_ID'),
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'cluster' => env('PUSHER_APP_CLUSTER'),
        'scheme' => env('PUSHER_SCHEME', 'https'),
        'host' => env('PUSHER_HOST'),
        'port' => env('PUSHER_PORT', 443),
    ],

    'ably' => [
        'key' => env('ABLY_KEY'),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'twilio'),
        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
            'from' => env('TWILIO_FROM'),
        ],
    ],

];
