<?php

namespace App\Jobs;

use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 2;

    public function handle(BackupService $backups): void
    {
        $path = $backups->createBackup();
        $verify = $backups->verifyBackup($path);

        if (! ($verify['ok'] ?? false)) {
            throw new \RuntimeException('Backup verify failed: '.($verify['message'] ?? 'unknown'));
        }

        Log::info('Backup created', [
            'file' => basename($path),
            'status' => $verify['manifest']['status'] ?? 'complete',
            'warnings' => $verify['manifest']['warnings'] ?? [],
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Backup job failed', [
            'message' => $exception?->getMessage(),
        ]);
    }
}
