<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class HomeFeedCache
{
    public const KEY = 'home_feed_v5';

    public static function forget(): void
    {
        foreach ([self::KEY, 'home_feed_v4', 'home_feed_v3'] as $key) {
            Cache::forget($key);
        }

        $paths = ['/api/v1/home-feed'];
        $appUrl = config('app.url');
        if (is_string($appUrl) && $appUrl !== '') {
            $paths[] = rtrim($appUrl, '/').'/api/v1/home-feed';
        }

        foreach ($paths as $path) {
            Cache::forget('response:'.md5($path));
        }
    }
}
