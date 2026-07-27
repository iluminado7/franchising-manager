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

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile
    |--------------------------------------------------------------------------
    |
    | Captcha del login. La sitekey es publica y vive en public/login.html;
    | aca va solo el secret, que nunca debe salir del backend.
    |
    | 'enabled' arranca en false a proposito: en dev (XAMPP) no hace falta
    | levantar el widget. En produccion hay que poner TURNSTILE_ENABLED=true
    | en el .env, sino el captcha queda decorativo.
    |
    */

    'turnstile' => [
        'enabled' => (bool) env('TURNSTILE_ENABLED', false),
        'secret'  => env('TURNSTILE_SECRET_KEY'),
        'timeout' => (int) env('TURNSTILE_TIMEOUT', 4),
    ],

];
