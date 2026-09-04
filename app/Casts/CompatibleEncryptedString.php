<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Encrypt api_key at rest while remaining readable for legacy plaintext rows.
 */
final class CompatibleEncryptedString implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = (string) $value;
        try {
            return Crypt::decryptString($raw);
        } catch (Throwable) {
            return $raw;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [$key => null];
        }

        return [$key => Crypt::encryptString((string) $value)];
    }
}
