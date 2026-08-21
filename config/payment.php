<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fake gateway (PHPUnit / local only)
    |--------------------------------------------------------------------------
    |
    | Never enable in production. When true, PaymentGatewayManager returns
    | FakePaymentGateway for every driver so tests never call real APIs.
    |
    */
    'fake' => filter_var(env('PAYMENT_FAKE', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Pending payment timeout
    |--------------------------------------------------------------------------
    |
    | Abandoned gateway transactions (no successful verify) are marked expired
    | after this many minutes. A late OK callback still attempts gateway verify.
    |
    */
    'pending_ttl_minutes' => (int) env('PAYMENT_PENDING_TTL_MINUTES', 45),

    /*
    |--------------------------------------------------------------------------
    | Wallet charge bounds (server-side; callback never trusts amount)
    |--------------------------------------------------------------------------
    */
    'min_wallet_charge' => (int) env('MIN_WALLET_CHARGE', 10000),
    'max_wallet_charge' => (int) env('MAX_WALLET_CHARGE', 50_000_000),

];
