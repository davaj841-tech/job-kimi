<?php

declare(strict_types=1);

namespace App\Services\Update;

use RuntimeException;

final class UpdateLock
{
    public function path(): string
    {
        $dir = storage_path('app/updates');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir.DIRECTORY_SEPARATOR.'update.lock';
    }

    public function acquire(string $updateUuid): void
    {
        $this->recoverIfStale();

        if (is_file($this->path())) {
            throw new RuntimeException('یک به‌روزرسانی دیگر در حال اجراست. لطفاً صبر کنید یا قفل منقضی را پاک کنید.');
        }

        $payload = json_encode([
            'uuid' => $updateUuid,
            'pid' => getmypid(),
            'started_at' => now()->toIso8601String(),
            'hostname' => gethostname(),
        ], JSON_UNESCAPED_UNICODE);

        if (@file_put_contents($this->path(), $payload, LOCK_EX) === false) {
            throw new RuntimeException('امکان ایجاد قفل به‌روزرسانی وجود ندارد.');
        }
    }

    public function release(): void
    {
        if (is_file($this->path())) {
            @unlink($this->path());
        }
    }

    public function isLocked(): bool
    {
        $this->recoverIfStale();

        return is_file($this->path());
    }

    /** @return array<string, mixed>|null */
    public function info(): ?array
    {
        if (! is_file($this->path())) {
            return null;
        }
        $data = json_decode((string) file_get_contents($this->path()), true);

        return is_array($data) ? $data : null;
    }

    public function recoverIfStale(): void
    {
        if (! is_file($this->path())) {
            return;
        }

        $ttl = (int) config('update.lock_ttl_seconds', 3600);
        $mtime = filemtime($this->path()) ?: 0;
        if ($mtime > 0 && (time() - $mtime) > $ttl) {
            @unlink($this->path());
        }
    }
}
