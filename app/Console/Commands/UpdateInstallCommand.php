<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Update\UpdateManager;
use Illuminate\Console\Command;
use Throwable;

class UpdateInstallCommand extends Command
{
    protected $signature = 'update:install {zip : Path to update ZIP}';

    protected $description = 'Install an update pack (same engine as Admin UI)';

    public function handle(UpdateManager $updates): int
    {
        $zip = $this->argument('zip');
        if (! is_file($zip)) {
            $this->error('ZIP not found: '.$zip);

            return self::FAILURE;
        }

        if (! $this->confirm('Install update from '.basename($zip).'?', true)) {
            return self::SUCCESS;
        }

        try {
            $update = $updates->installFromZip($zip, null);
            $this->info('Installed '.$update->version.' ['.$update->status.']');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
