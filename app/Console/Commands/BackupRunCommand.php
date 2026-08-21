<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    protected $signature = 'backup:run
                            {--verify= : Verify an existing backup ZIP by basename or absolute path}
                            {--keep= : Override BACKUP_KEEP_COUNT for this run}';

    protected $description = 'Create a verified application backup (database + private + public files)';

    public function handle(BackupService $backups): int
    {
        if ($this->option('verify')) {
            $path = (string) $this->option('verify');
            if (! is_file($path)) {
                try {
                    $path = $backups->resolvePath($path);
                } catch (\Throwable $e) {
                    $this->error($e->getMessage());

                    return self::FAILURE;
                }
            }

            $result = $backups->verifyBackup($path);
            if ($result['ok']) {
                $this->info($result['message']);
                if (! empty($result['manifest']['status'])) {
                    $this->line('Status: '.$result['manifest']['status']);
                }

                return self::SUCCESS;
            }

            $this->error($result['message']);

            return self::FAILURE;
        }

        $keep = $this->option('keep');
        if ($keep !== null && $keep !== '') {
            $backups->cleanupOldBackups(max(1, (int) $keep));
        }

        $this->info('Creating backup...');

        try {
            $path = $backups->createBackup();
        } catch (\Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup saved: '.basename($path));
        $verify = $backups->verifyBackup($path);
        if (! ($verify['ok'] ?? false)) {
            $this->error('Post-create verify failed: '.($verify['message'] ?? ''));

            return self::FAILURE;
        }

        $this->info('Verified OK');
        if (! empty($verify['manifest']['warnings'])) {
            foreach ($verify['manifest']['warnings'] as $warning) {
                $this->warn('Warning: '.$warning);
            }
        }

        return self::SUCCESS;
    }
}
