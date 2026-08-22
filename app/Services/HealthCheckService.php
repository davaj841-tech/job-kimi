<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthCheckService
{
    /**
     * @return array{
     *     status: 'ok'|'degraded'|'down',
     *     timestamp: string,
     *     checks: array<string, array<string, mixed>>,
     *     http_status: int
     * }
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        $databaseOk = ($checks['database']['status'] ?? '') === 'ok';
        $redisOk = ($checks['redis']['status'] ?? '') === 'ok';
        $queueOk = ($checks['queue']['status'] ?? '') === 'ok';
        $storageOk = ($checks['storage']['status'] ?? '') === 'ok';

        if (! $databaseOk) {
            $status = 'down';
            $httpStatus = 503;
        } elseif (! $redisOk || ! $queueOk || ! $storageOk) {
            $status = 'degraded';
            $httpStatus = 200;
        } else {
            $status = 'ok';
            $httpStatus = 200;
        }

        return [
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'http_status' => $httpStatus,
        ];
    }

    /**
     * @return array{status: string, response_time_ms: int, error?: string}
     */
    public function checkDatabase(): array
    {
        $started = hrtime(true);

        try {
            DB::select('SELECT 1');

            return [
                'status' => 'ok',
                'response_time_ms' => $this->elapsedMs($started),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'fail',
                'response_time_ms' => $this->elapsedMs($started),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, response_time_ms: int, error?: string}
     */
    public function checkRedis(): array
    {
        $started = hrtime(true);
        $required = $this->redisIsRequired();

        try {
            Redis::connection()->ping();

            return [
                'status' => 'ok',
                'response_time_ms' => $this->elapsedMs($started),
            ];
        } catch (Throwable $e) {
            // در محیط‌هایی که Redis اجباری نیست (مثلاً testing با array/sync)،
            // قطع بودن Redis را fail گزارش نکن تا status اشتباه down/degraded نشود.
            if (! $required) {
                return [
                    'status' => 'ok',
                    'response_time_ms' => $this->elapsedMs($started),
                ];
            }

            return [
                'status' => 'fail',
                'response_time_ms' => $this->elapsedMs($started),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, pending_jobs: int, response_time_ms?: int, error?: string}
     */
    public function checkQueue(): array
    {
        $started = hrtime(true);
        $driver = (string) config('queue.default', 'sync');

        try {
            if ($driver === 'sync' || $driver === 'null') {
                return [
                    'status' => 'ok',
                    'pending_jobs' => 0,
                    'response_time_ms' => $this->elapsedMs($started),
                ];
            }

            if ($driver === 'redis') {
                Redis::connection()->ping();
                $pending = $this->countRedisPendingJobs();

                return [
                    'status' => 'ok',
                    'pending_jobs' => $pending,
                    'response_time_ms' => $this->elapsedMs($started),
                ];
            }

            if ($driver === 'database') {
                $table = config('queue.connections.database.table', 'jobs');
                $pending = (int) DB::table($table)->count();

                return [
                    'status' => 'ok',
                    'pending_jobs' => $pending,
                    'response_time_ms' => $this->elapsedMs($started),
                ];
            }

            return [
                'status' => 'ok',
                'pending_jobs' => 0,
                'response_time_ms' => $this->elapsedMs($started),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'fail',
                'pending_jobs' => 0,
                'response_time_ms' => $this->elapsedMs($started),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, writable: bool, response_time_ms: int, error?: string}
     */
    public function checkStorage(): array
    {
        $started = hrtime(true);

        try {
            $path = '.healthcheck';
            Storage::disk('local')->put($path, (string) time());
            $writable = Storage::disk('local')->exists($path);

            return [
                'status' => $writable ? 'ok' : 'fail',
                'writable' => $writable,
                'response_time_ms' => $this->elapsedMs($started),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'fail',
                'writable' => false,
                'response_time_ms' => $this->elapsedMs($started),
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function redisIsRequired(): bool
    {
        return in_array(config('cache.default'), ['redis'], true)
            || config('queue.default') === 'redis'
            || config('session.driver') === 'redis';
    }

    protected function countRedisPendingJobs(): int
    {
        try {
            $connection = config('queue.connections.redis.connection', 'default');
            $queue = config('queue.connections.redis.queue', 'default');

            // Laravel Redis queue keys vary; best-effort pending estimate.
            $size = Redis::connection($connection)->llen('queues:'.$queue);

            return is_numeric($size) ? (int) $size : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    protected function elapsedMs(int $startedHrtime): int
    {
        return (int) max(0, round((hrtime(true) - $startedHrtime) / 1_000_000));
    }
}
