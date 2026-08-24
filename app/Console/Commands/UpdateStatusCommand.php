<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SystemUpdate;
use App\Services\Update\UpdateManager;
use Illuminate\Console\Command;

class UpdateStatusCommand extends Command
{
    protected $signature = 'update:status';

    protected $description = 'Show current application version and update lock/health status';

    public function handle(UpdateManager $updates): int
    {
        $status = $updates->status();
        $this->info('Current version: '.$status['current_version']);
        $this->line('Locked: '.(($status['locked'] ?? false) ? 'yes' : 'no'));
        $this->line('Health: '.(($status['health']['ok'] ?? false) ? 'ok' : 'fail'));
        foreach (($status['health']['checks'] ?? []) as $k => $v) {
            $this->line("  - {$k}: {$v}");
        }
        if ($status['last'] instanceof SystemUpdate) {
            $this->line('Last update: '.$status['last']->version.' ['.$status['last']->status.']');
        }

        return self::SUCCESS;
    }
}
