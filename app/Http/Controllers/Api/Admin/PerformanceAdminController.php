<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PerformanceAdminController extends BaseController
{
    public function status(): JsonResponse
    {
        return $this->successResponse([
            'auto' => Setting::getBool('performance_auto', false),
            'last_boost_at' => Setting::get('performance_last_boost_at'),
        ]);
    }

    public function boost(): JsonResponse
    {
        $log = $this->runBoost();

        return $this->successResponse($log, 'بهینه‌سازی سرعت انجام شد.');
    }

    public function auto(): JsonResponse
    {
        $on = ! Setting::getBool('performance_auto', false);
        Setting::set('performance_auto', $on ? 'true' : 'false', 'performance');
        $log = [];
        if ($on) {
            $log = $this->runBoost();
        }

        return $this->successResponse([
            'auto' => $on,
            'log' => $log,
        ], $on ? 'سرعت خودکار فعال شد.' : 'سرعت خودکار خاموش شد.');
    }

    /**
     * @return list<string>
     */
    /**
     * @return list<string>
     */
    protected function runBoost(): array
    {
        $log = [];

        try {
            Artisan::call('view:cache');
            $log[] = 'کش قالب‌ها';
        } catch (\Throwable $e) {
            $log[] = 'قالب: '.$e->getMessage();
        }

        if (! app()->environment('local', 'testing')) {
            try {
                Artisan::call('config:cache');
                $log[] = 'کش تنظیمات';
            } catch (\Throwable $e) {
                $log[] = 'تنظیمات: '.$e->getMessage();
            }
            try {
                Artisan::call('route:cache');
                $log[] = 'کش مسیرها';
            } catch (\Throwable $e) {
                $log[] = 'مسیرها: '.$e->getMessage();
            }
            try {
                Artisan::call('optimize');
                $log[] = 'optimize';
            } catch (\Throwable) {
            }
        }

        $this->warmPublicApis($log);

        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $log[] = 'بازنشانی OPcache';
        }

        Setting::set('performance_last_boost_at', now()->toIso8601String(), 'performance');
        Cache::forget('public_theme_bootstrap');
        Cache::forget('public_settings_payload');

        return $log;
    }

    /**
     * @param  list<string>  $log
     */
    /**
     * @param  list<string>  $log
     */
    /**
     * @param  list<string>  $log
     */
    protected function warmPublicApis(array &$log): void
    {
        $base = rtrim((string) config('app.url'), '/');
        $paths = [
            '/api/v1/home-feed',
            '/api/v1/settings/public',
            '/api/v1/banners?position=home_hero',
            '/api/v1/exams',
            '/api/v1/job-posts',
        ];
        $ok = 0;
        foreach ($paths as $path) {
            try {
                $res = Http::timeout(8)->acceptJson()->get($base.$path);
                if ($res->successful()) {
                    $ok++;
                }
            } catch (\Throwable) {
            }
        }
        $log[] = "گرم‌کردن API ({$ok}/".count($paths).')';
    }
}
