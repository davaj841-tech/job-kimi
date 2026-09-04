<?php

declare(strict_types=1);

namespace App\Services\Update;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class UpdateHealthChecker
{
    /**
     * @return array{ok: bool, checks: array<string, string>, version: string}
     */
    public function check(): array
    {
        $checks = [
            'php' => version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'fail',
            'laravel' => class_exists(Application::class) ? 'ok' : 'fail',
            'database' => $this->database(),
            'storage' => $this->storage(),
            'cache' => $this->cache(),
            'queue' => $this->queue(),
            'version' => SemVer::isValid(SemVer::current()) ? 'ok' : 'fail',
        ];

        $critical = ['php', 'laravel', 'database', 'storage', 'version'];
        $ok = true;
        foreach ($critical as $key) {
            if (($checks[$key] ?? 'fail') === 'fail') {
                $ok = false;
                break;
            }
        }

        return [
            'ok' => $ok,
            'checks' => $checks,
            'version' => SemVer::current(),
        ];
    }

    private function database(): string
    {
        try {
            DB::select('SELECT 1');

            return 'ok';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    private function storage(): string
    {
        try {
            Storage::disk('local')->put('.update-health', (string) time());

            return Storage::disk('local')->exists('.update-health') ? 'ok' : 'fail';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    private function cache(): string
    {
        try {
            cache()->put('update_health', 1, 10);

            return cache()->get('update_health') === 1 ? 'ok' : 'fail';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    private function queue(): string
    {
        try {
            $driver = config('queue.default');
            if ($driver === 'redis') {
                Redis::connection()->ping();
            } elseif ($driver === 'database') {
                DB::table(config('queue.connections.database.table', 'jobs'))->limit(1)->count();
            }

            return 'ok';
        } catch (\Throwable) {
            return 'fail';
        }
    }
}
