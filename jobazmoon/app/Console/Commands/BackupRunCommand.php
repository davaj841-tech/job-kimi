<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Create application backup (database + PDFs + resumes)';

    public function handle(BackupService $backups): int
    {
        $this->info('Creating backup...');
        $path = $backups->createBackup();
        $this->info('Backup saved: '.$path);

        return self::SUCCESS;
    }
}
