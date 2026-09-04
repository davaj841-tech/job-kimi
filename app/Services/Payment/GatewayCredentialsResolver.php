<?php

namespace App\Services\Payment;

use App\Models\PaymentGateway;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resolve gateway credentials from Admin Settings → payment_gateways row → env/config.
 * Never logs secret values.
 *
 * Preference matches project convention: filled Admin Settings override .env.
 */
final class GatewayCredentialsResolver
{
    /**
     * @param  list<string>  $settingKeys  Preference order of Setting keys
     * @param  list<string>  $envConfigPaths  Dot paths under config('services.{gateway}.*')
     */
    public function get(string $gateway, array $settingKeys = [], array $envConfigPaths = [], ?string $rowColumn = null): string
    {
        foreach ($settingKeys as $key) {
            $value = Setting::getFilled($key, null);
            if ($this->filled($value) && ! $this->isMaskedPlaceholder((string) $value)) {
                return (string) $value;
            }
        }

        $row = PaymentGateway::query()->where('name', $gateway)->first();
        if ($row) {
            if ($rowColumn === 'merchant_id' && $this->filled($row->merchant_id)) {
                return (string) $row->merchant_id;
            }
            if ($rowColumn === 'api_key' && $this->filled($row->api_key)) {
                return (string) $row->api_key;
            }

            $settings = $this->decryptSettings($row->settings ?? null);
            foreach ($settingKeys as $key) {
                $short = Str::after($key, $gateway.'_');
                foreach ([$key, $short, Str::snake(Str::afterLast($key, '_'))] as $candidate) {
                    if (isset($settings[$candidate]) && $this->filled($settings[$candidate])
                        && ! $this->isMaskedPlaceholder((string) $settings[$candidate])) {
                        return (string) $settings[$candidate];
                    }
                }
            }
        }

        foreach ($envConfigPaths as $path) {
            $value = config('services.'.$gateway.'.'.$path);
            if ($this->filled($value)) {
                return (string) $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    public function decryptSettings(mixed $settings): array
    {
        if (! is_array($settings)) {
            return [];
        }

        $out = [];
        foreach ($settings as $key => $value) {
            if (! is_string($value)) {
                $out[$key] = $value;

                continue;
            }
            if ($this->looksEncrypted($value)) {
                try {
                    $out[$key] = Crypt::decryptString($value);
                } catch (Throwable) {
                    $out[$key] = $value;
                }
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<string>  $secretKeys
     * @return array<string, mixed>
     */
    public function encryptSettings(array $settings, array $secretKeys = []): array
    {
        $out = [];
        foreach ($settings as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if ($this->isMaskedPlaceholder((string) $value)) {
                continue;
            }
            $string = is_scalar($value) ? (string) $value : json_encode($value);
            if ($string === false) {
                continue;
            }
            if (in_array($key, $secretKeys, true) || Str::contains($key, ['password', 'secret', 'key', 'pin'])) {
                $out[$key] = Crypt::encryptString($string);
            } else {
                $out[$key] = $string;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<string>  $secretKeys
     * @return array<string, mixed>
     */
    public function maskForAdmin(array $settings, array $secretKeys = []): array
    {
        $plain = $this->decryptSettings($settings);
        $out = [];
        foreach ($plain as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }
            $str = (string) $value;
            if ($str === '') {
                $out[$key] = '';

                continue;
            }
            if (in_array($key, $secretKeys, true) || Str::contains($key, ['password', 'secret', 'key', 'pin'])) {
                $out[$key] = $this->maskSecret($str);
            } else {
                $out[$key] = $str;
            }
        }

        return $out;
    }

    public function isMaskedPlaceholder(string $value): bool
    {
        return $value === '' || str_contains($value, '*') || preg_match('/^\*+$/', $value) === 1;
    }

    public function maskSecret(string $value): string
    {
        $len = mb_strlen($value);
        if ($len <= 4) {
            return str_repeat('*', max(4, $len));
        }

        return mb_substr($value, 0, 2).str_repeat('*', min(12, $len - 4)).mb_substr($value, -2);
    }

    protected function filled(mixed $value): bool
    {
        return is_string($value) ? trim($value) !== '' : filled($value);
    }

    protected function looksEncrypted(string $value): bool
    {
        return str_starts_with($value, 'eyJpdiI6') || (strlen($value) > 40 && str_contains($value, ':'));
    }
}
