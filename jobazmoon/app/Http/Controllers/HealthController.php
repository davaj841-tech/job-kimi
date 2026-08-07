<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
        ];

        $healthy = ! in_array('fail', $checks, true);
        $status = $healthy ? 'healthy' : 'unhealthy';

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    protected function checkDatabase(): string
    {
        try {
            DB::select('SELECT 1');

            return 'ok';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    protected function checkRedis(): string
    {
        $required = in_array(config('cache.default'), ['redis'], true)
            || config('queue.default') === 'redis'
            || config('session.driver') === 'redis';

        try {
            Redis::connection()->ping();

            return 'ok';
        } catch (\Throwable) {
            return $required ? 'fail' : 'ok';
        }
    }

    protected function checkStorage(): string
    {
        try {
            Storage::disk('local')->put('.healthcheck', (string) time());
            $ok = Storage::disk('local')->exists('.healthcheck');

            return $ok ? 'ok' : 'fail';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    protected function checkQueue(): string
    {
        try {
            $driver = config('queue.default');
            if ($driver === 'redis') {
                Redis::connection()->ping();

                return 'ok';
            }
            if ($driver === 'database') {
                DB::table(config('queue.connections.database.table', 'jobs'))->limit(1)->count();

                return 'ok';
            }

            return 'ok';
        } catch (\Throwable) {
            return 'fail';
        }
    }
}
