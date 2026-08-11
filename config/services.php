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

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sms' => [
        'gateway' => env('SMS_GATEWAY', 'kavenegar'),
        // local/testing may log OTP instead of sending; production must fail closed.
        'allow_log_fallback' => env('SMS_ALLOW_LOG_FALLBACK', env('APP_ENV') !== 'production'),
    ],

    'kavenegar' => [
        'api_key' => env('KAVENEGAR_API_KEY'),
    ],

    'melipayamak' => [
        'username' => env('MELIPAYAMAK_USERNAME'),
        'password' => env('MELIPAYAMAK_PASSWORD'),
        'from' => env('MELIPAYAMAK_FROM'),
    ],

    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
        'sandbox' => env('ZARINPAL_SANDBOX', false),
    ],

    'nextpay' => [
        'api_key' => env('NEXTPAY_API_KEY'),
    ],

    'idpay' => [
        'api_key' => env('IDPAY_API_KEY'),
        'sandbox' => env('IDPAY_SANDBOX', false),
    ],

];
