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

    'twilio' => [
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
    ],

    'eleven-labs' => [
        'api_key' => env('ELEVEN_LABS_API_KEY'),
        'voices' => [
            'en' => env('ELEVEN_LABS_VOICE_EN', 'MFZUKuGQUsGJPQjTS4wC'),
            'es' => env('ELEVEN_LABS_VOICE_ES', 'gbTn1bmCvNgk0QEAVyfM'),
        ]
    ],

    'deepgram' => [
        'api_key' => env('DEEPGRAM_API_KEY'),
    ],

    'go_websocket_server' => [
        'host' => env('GO_WEBSOCKET_SERVER_HOST'),
        'port' => env('GO_WEBSOCKET_SERVER_PORT'),
    ]

];
