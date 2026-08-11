<?php

declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\TransactionFailedException;
use App\Services\SiteErrorLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

trait HandlesTransactions
{
    /**
     * Execute callback inside a database transaction with deadlock retry.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     *
     * @throws TransactionFailedException
     */
    public function transaction(
        callable $callback,
        ?int $attempts = null,
        ?string $isolationLevel = null,
    ): mixed {
        $attempts = max(1, $attempts ?? (int) config('database.transactions.attempts', 3));
        $isolationLevel = $isolationLevel
            ?? (string) config('database.transactions.isolation', 'REPEATABLE READ');

        $allowedIsolation = ['REPEATABLE READ', 'SERIALIZABLE', 'READ COMMITTED', 'READ UNCOMMITTED'];
        if (! in_array(strtoupper($isolationLevel), $allowedIsolation, true)) {
            $isolationLevel = 'REPEATABLE READ';
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $this->applyIsolationLevel($isolationLevel);

                return DB::transaction(static fn () => $callback());
            } catch (Throwable $e) {
                $lastException = $e;

                app(SiteErrorLogger::class)->report($e, [
                    'source' => static::class,
                    'attempt' => $attempt,
                    'max_attempts' => $attempts,
                    'isolation' => $isolationLevel,
                    'deadlock' => $this->isDeadlock($e),
                ]);

                if (! $this->isDeadlock($e) || $attempt >= $attempts) {
                    if ($this->isDeadlock($e)) {
                        throw new TransactionFailedException(
                            'Transaction failed after deadlock retries',
                            $e,
                            [
                                'attempts' => $attempt,
                                'isolation' => $isolationLevel,
                            ]
                        );
                    }

                    throw $e;
                }

                usleep(25_000 * $attempt);
            }
        }

        throw new TransactionFailedException(
            'Transaction failed after retries',
            $lastException,
            [
                'attempts' => $attempts,
                'isolation' => $isolationLevel,
            ]
        );
    }

    private function applyIsolationLevel(string $isolationLevel): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL '.$isolationLevel);

        $lockWait = (int) config('database.transactions.lock_wait_timeout', 10);
        if ($lockWait > 0) {
            DB::statement('SET SESSION innodb_lock_wait_timeout = '.(string) $lockWait);
        }
    }

    private function isDeadlock(Throwable $e): bool
    {
        if ($e instanceof QueryException) {
            $sqlState = (string) ($e->errorInfo[0] ?? '');
            $driverCode = (int) ($e->errorInfo[1] ?? 0);

            if ($driverCode === 1213 || $sqlState === '40001') {
                return true;
            }
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'deadlock')
            || str_contains($message, 'serialization failure');
    }
}
