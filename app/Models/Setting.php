<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    /** خواندن تنظیمات از دیتابیس (بدون هاردکد) */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting.{$key}", 3600, function () use ($key, $default) {
            $setting = static::query()->where('key', $key)->first();

            return $setting?->value ?? $default;
        });
    }

    /** مقدار خالی دیتابیس را نادیده می‌گیرد تا fallback به env/config کار کند. */
    public static function getFilled(string $key, mixed $default = null): mixed
    {
        $value = static::get($key);

        return filled($value) ? $value : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );

        Cache::forget("setting.{$key}");
        Cache::forget('public_theme_bootstrap');
        Cache::forget('public_settings_payload');
    }
}
