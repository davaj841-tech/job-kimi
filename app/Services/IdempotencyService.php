<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\IdempotencyException;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IdempotencyService
{
    public function generateKey(): string
    {
        return (string) Str::uuid();
    }

    public function isProcessed(string $key): bool
    {
        $cacheKey = $this->cacheKey($key);

        if (Cache::get($cacheKey) === true) {
            return true;
        }

        $processed = Transaction::query()
            ->byIdempotencyKey($key)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->exists();

        if ($processed) {
            Cache::put($cacheKey, true, (int) config('idempotency.cache_ttl', 3600));
        }

        return $processed;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function markProcessed(string $key, array $context = []): void
    {
        $updated = Transaction::query()
            ->byIdempotencyKey($key)
            ->whereIn('status', [Transaction::STATUS_PENDING, Transaction::STATUS_FAILED])
            ->update(array_merge($context, [
                'status' => Transaction::STATUS_COMPLETED,
            ]));

        if ($updated === 0 && ! $this->isProcessed($key)) {
            throw new IdempotencyException("Unable to mark idempotency key as processed: {$key}");
        }

        Cache::put($this->cacheKey($key), true, (int) config('idempotency.cache_ttl', 3600));
    }

    public function getTransaction(string $key): ?Transaction
    {
        return Transaction::query()->byIdempotencyKey($key)->first();
    }

    public function extractKey(Request $request): ?string
    {
        $key = $request->query('ik')
            ?? $request->input('ik')
            ?? $request->query('idempotency_key')
            ?? $request->input('idempotency_key');

        if (! is_string($key) || $key === '') {
            return null;
        }

        return $key;
    }

    public function appendKeyToCallback(string $callbackUrl, string $key): string
    {
        $separator = str_contains($callbackUrl, '?') ? '&' : '?';

        return $callbackUrl.$separator.'ik='.urlencode($key);
    }

    /**
     * اگر کلید قبلاً پردازش شده باشد، خطای Conflict (۴۰۹) پرتاب می‌کند.
     */
    public function ensureUnique(string $key): void
    {
        if ($this->isProcessed($key)) {
            throw new IdempotencyException('Idempotency key already processed.', 409);
        }
    }

    /**
     * گرفتن قفل کوتاه‌مدت روی کلید (برای جلوگیری از پردازش موازی).
     */
    public function acquireLock(string $key, ?int $seconds = null): bool
    {
        $seconds = max(1, $seconds ?? (int) config('idempotency.lock_timeout', 10));

        return Cache::lock($this->lockCacheKey($key), $seconds)->get();
    }

    /**
     * آزادسازی قفل کلید.
     */
    public function releaseLock(string $key): void
    {
        Cache::lock($this->lockCacheKey($key))->forceRelease();
    }

    /**
     * آیا قفل هنوز فعال است؟
     */
    public function isLocked(string $key): bool
    {
        $lock = Cache::lock($this->lockCacheKey($key), 1);
        if (! $lock->get()) {
            return true;
        }
        $lock->release();

        return false;
    }

    /**
     * Lock the transaction and run side effects at most once.
     *
     * @template T
     *
     * @param  callable(Transaction): T  $sideEffects
     * @return array{already_processed: bool, transaction: Transaction, result: T|null}
     */
    public function completeOnce(Transaction $transaction, callable $sideEffects): array
    {
        $attempts = max(1, (int) config('idempotency.lock_timeout', 10));

        return DB::transaction(function () use ($transaction, $sideEffects) {
            /** @var Transaction $locked */
            $locked = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === Transaction::STATUS_COMPLETED) {
                if (is_string($locked->idempotency_key) && $locked->idempotency_key !== '') {
                    Cache::put(
                        $this->cacheKey($locked->idempotency_key),
                        true,
                        (int) config('idempotency.cache_ttl', 3600)
                    );
                }

                return [
                    'already_processed' => true,
                    'transaction' => $locked,
                    'result' => null,
                ];
            }

            if (in_array($locked->status, [Transaction::STATUS_CANCELLED, Transaction::STATUS_EXPIRED], true)) {
                throw new IdempotencyException('Transaction was cancelled or expired.');
            }

            if (! in_array($locked->status, [Transaction::STATUS_PENDING, Transaction::STATUS_FAILED], true)) {
                throw new IdempotencyException('Transaction is not eligible for processing.');
            }

            $result = $sideEffects($locked);

            $locked->refresh();

            if ($locked->status !== Transaction::STATUS_COMPLETED) {
                $locked->update(['status' => Transaction::STATUS_COMPLETED]);
                $locked->refresh();
            }

            if (is_string($locked->idempotency_key) && $locked->idempotency_key !== '') {
                Cache::put(
                    $this->cacheKey($locked->idempotency_key),
                    true,
                    (int) config('idempotency.cache_ttl', 3600)
                );
            }

            return [
                'already_processed' => false,
                'transaction' => $locked,
                'result' => $result,
            ];
        }, $attempts);
    }

    private function cacheKey(string $key): string
    {
        return 'idempotency:processed:'.$key;
    }

    private function lockCacheKey(string $key): string
    {
        return 'idempotency:lock:'.$key;
    }
}
