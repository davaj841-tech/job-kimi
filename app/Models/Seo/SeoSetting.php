<?php

namespace App\Models\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SeoSetting extends Model
{
    protected $table = 'seo_settings';

    protected $guarded = ['id'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("seo_setting:{$key}", 3600, function () use ($key, $default) {
            $setting = static::query()->where('key', $key)->first();
            if ($setting === null) {
                return $default;
            }

            return $setting->value ?? $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("seo_setting:{$key}");
    }
}
