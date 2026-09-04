<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS master switch
    |--------------------------------------------------------------------------
    */
    'enabled' => filter_var(env('SMS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'provider' => env('SMS_PROVIDER', env('SMS_GATEWAY', 'melipayamak')),

    'timeout' => (int) env('SMS_TIMEOUT', 10),

    /*
    | Dev/test only: when credentials missing, log and return success.
    | Production must keep this false.
    */
    'allow_log_fallback' => filter_var(
        env('SMS_ALLOW_LOG_FALLBACK', env('APP_ENV') !== 'production'),
        FILTER_VALIDATE_BOOLEAN
    ),

    'allow_legacy_plaintext_otp' => filter_var(
        env('SMS_ALLOW_LEGACY_PLAINTEXT_OTP', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    | Feature flags (admin settings may override via Setting model)
    */
    'features' => [
        'otp' => filter_var(env('SMS_OTP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'transactional' => filter_var(env('SMS_TRANSACTIONAL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'marketing' => filter_var(env('SMS_MARKETING_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    | Queue non-OTP SMS when true (OTP stays synchronous for UX).
    */
    'queue' => [
        'enabled' => filter_var(env('SMS_QUEUE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'connection' => env('SMS_QUEUE_CONNECTION'),
        'queue' => env('SMS_QUEUE_NAME', 'default'),
        'tries' => (int) env('SMS_QUEUE_TRIES', 3),
        'backoff' => array_map('intval', array_filter(explode(',', env('SMS_QUEUE_BACKOFF', '30,120,300')))),
    ],

    'otp' => [
        'length' => (int) env('SMS_OTP_LENGTH', 5),
        'expires_minutes' => (int) env('SMS_OTP_EXPIRES_MINUTES', 3),
        'resend_cooldown_seconds' => (int) env('SMS_OTP_RESEND_COOLDOWN', 60),
        'daily_limit' => (int) env('SMS_OTP_DAILY_LIMIT', 10),
        'max_verify_attempts' => (int) env('SMS_OTP_MAX_VERIFY_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('SMS_OTP_LOCKOUT_MINUTES', 15),
        'template' => env('SMS_OTP_TEMPLATE', 'کد تایید جاب‌آزمون: {code}'),
    ],

    'logging' => [
        'enabled' => filter_var(env('SMS_LOG_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'store_body' => filter_var(env('SMS_LOG_STORE_BODY', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'melipayamak' => [
        'username' => env('MELIPAYAMAK_USERNAME'),
        'password' => env('MELIPAYAMAK_PASSWORD'),
        'from' => env('MELIPAYAMAK_FROM'),
        'api_url' => env('MELIPAYAMAK_API_URL', 'https://rest.payamak-panel.com/api/SendSMS'),
        'pattern_body_id' => env('MELIPAYAMAK_PATTERN_BODY_ID'),
        'pattern_text' => env('MELIPAYAMAK_PATTERN_TEXT', '{code}'),
    ],

    'kavenegar' => [
        'api_key' => env('KAVENEGAR_API_KEY'),
    ],

    /*
    | Legacy keys — kept for backward compatibility with config('services.sms.*)
    */
    'gateway' => env('SMS_GATEWAY', env('SMS_PROVIDER', 'melipayamak')),
    'otp_template' => env('SMS_OTP_TEMPLATE', 'کد تایید جاب‌آزمون: {code}'),

];
