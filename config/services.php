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
        'gateway' => env('SMS_GATEWAY', env('SMS_PROVIDER', 'melipayamak')),
        // local/testing may log OTP instead of sending; production must fail closed.
        'allow_log_fallback' => env('SMS_ALLOW_LOG_FALLBACK', env('APP_ENV') !== 'production'),
        'allow_legacy_plaintext_otp' => env('SMS_ALLOW_LEGACY_PLAINTEXT_OTP', false),
        'timeout' => (int) env('SMS_TIMEOUT', 10),
        'otp_template' => env('SMS_OTP_TEMPLATE', 'کد تایید جاب‌آزمون: {code}'),
        'enabled' => filter_var(env('SMS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'kavenegar' => [
        'api_key' => env('KAVENEGAR_API_KEY'),
    ],

    'melipayamak' => [
        'username' => env('MELIPAYAMAK_USERNAME'),
        'password' => env('MELIPAYAMAK_PASSWORD'),
        'from' => env('MELIPAYAMAK_FROM'),
        'api_url' => env('MELIPAYAMAK_API_URL', 'https://rest.payamak-panel.com/api/SendSMS'),
        // Approved pattern bodyId for shared service line OTP (BaseServiceNumber).
        'pattern_body_id' => env('MELIPAYAMAK_PATTERN_BODY_ID'),
        // Pattern variable text; use {code} for OTP. Multiple vars: "{code};name"
        'pattern_text' => env('MELIPAYAMAK_PATTERN_TEXT', '{code}'),
    ],

    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
        'sandbox' => env('ZARINPAL_SANDBOX', false),
        'timeout' => (int) env('ZARINPAL_TIMEOUT', 15),
        'currency' => env('ZARINPAL_CURRENCY', 'IRR'),
        'base_url' => env('ZARINPAL_BASE_URL'),
        'sandbox_base_url' => env('ZARINPAL_SANDBOX_BASE_URL', 'https://sandbox.zarinpal.com'),
    ],

    'nextpay' => [
        'api_key' => env('NEXTPAY_API_KEY'),
    ],

    'idpay' => [
        'api_key' => env('IDPAY_API_KEY'),
        'sandbox' => env('IDPAY_SANDBOX', false),
    ],

    'mellat' => [
        'terminal_id' => env('MELLAT_TERMINAL_ID'),
        'username' => env('MELLAT_USERNAME'),
        'password' => env('MELLAT_PASSWORD'),
    ],

    'shaparak' => [
        'terminal_id' => env('SHAPARAK_TERMINAL_ID'),
        'username' => env('SHAPARAK_USERNAME'),
        'password' => env('SHAPARAK_PASSWORD'),
    ],

    'turnstile' => [
        // Public site key — overridable via Admin Settings (turnstile_site_key).
        'site_key' => env('TURNSTILE_SITE_KEY', '0x4AAAAAAEiAUvpbmcvevZFU'),
        // Secret ONLY from .env — never Admin Settings / frontend / logs.
        'secret' => env('TURNSTILE_SECRET_KEY'),
        // Bootstrap default when DB setting turnstile_enabled is unset.
        'enabled' => filter_var(env('TURNSTILE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        // Comma-separated hostnames allowed in siteverify response (plus APP_URL host).
        'hostnames' => array_values(array_filter(array_map(
            static fn (string $h): string => strtolower(trim($h)),
            explode(',', (string) env('TURNSTILE_HOSTNAMES', ''))
        ))),
        'timeout' => (int) env('TURNSTILE_TIMEOUT', 10),
        'max_token_age_seconds' => (int) env('TURNSTILE_MAX_TOKEN_AGE', 300),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4'),
    ],

];
