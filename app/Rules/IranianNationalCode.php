<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IranianNationalCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! self::isValid($value)) {
            $fail('کد ملی واردشده معتبر نیست.');
        }
    }

    public static function isValid(mixed $value): bool
    {
        $raw = preg_replace('/\D+/', '', (string) $value) ?? '';
        if (! preg_match('/^\d{10}$/', $raw)) {
            return false;
        }

        if (preg_match('/^(\d)\1{9}$/', $raw)) {
            return false;
        }

        $check = (int) $raw[9];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $raw[$i]) * (10 - $i);
        }
        $remainder = $sum % 11;

        return $remainder < 2 ? $check === $remainder : $check === 11 - $remainder;
    }
}
