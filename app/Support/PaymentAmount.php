<?php

namespace App\Support;

/**
 * Central payment amount unit — all server-side amounts are rials (IRR).
 * ZarinPal v4 API expects IRR. Do not convert in controllers or frontend.
 */
final class PaymentAmount
{
    public static function unit(): string
    {
        return (string) config('payment.amount_unit', 'rial');
    }

    public static function currency(): string
    {
        return (string) config('payment.currency', 'IRR');
    }

    public static function minWalletCharge(): int
    {
        return max(1000, (int) config('payment.min_wallet_charge', 10000));
    }

    public static function maxWalletCharge(): int
    {
        return max(self::minWalletCharge(), (int) config('payment.max_wallet_charge', 50_000_000));
    }

    public static function isValidWalletCharge(int $amount): bool
    {
        return $amount >= self::minWalletCharge() && $amount <= self::maxWalletCharge();
    }

    public static function isValidGatewayAmount(int $amount): bool
    {
        return $amount >= 1000;
    }

    public static function format(int $amount): string
    {
        return number_format($amount).' ریال';
    }
}
