<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Clears public job-posts list caches (Laravel + CacheResponse middleware).
 */
class JobPostsCache
{
    public static function forget(): void
    {
        HomeFeedCache::forget();

        $paths = ['/api/v1/job-posts', '/api/v1/job-posts/filters'];
        $appUrl = config('app.url');
        if (is_string($appUrl) && $appUrl !== '') {
            $base = rtrim($appUrl, '/');
            $paths[] = $base.'/api/v1/job-posts';
            $paths[] = $base.'/api/v1/job-posts/filters';
        }

        foreach ($paths as $path) {
            Cache::forget('response:'.md5($path));
        }
    }
}
