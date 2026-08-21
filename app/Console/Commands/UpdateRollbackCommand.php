<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SystemUpdate;
use App\Services\Update\UpdateManager;
use Illuminate\Console\Command;
use Throwable;

class UpdateRollbackCommand extends Command
{
    protected $signature = 'update:rollback {id? : System update ID (default: latest failed/completed)}';

    protected $description = 'Rollback a system update using its backups';

    public function handle(UpdateManager $updates): int
    {
        $id = $this->argument('id');
        $update = $id
            ? SystemUpdate::query()->findOrFail($id)
            : SystemUpdate::query()
                ->whereIn('status', [SystemUpdate::FAILED, SystemUpdate::COMPLETED, SystemUpdate::ROLLED_BACK])
                ->latest('id')
                ->first();

        if (! $update) {
            $this->error('No update found to rollback.');

            return self::FAILURE;
        }

        if (! $this->confirm("Rollback update #{$update->id} ({$update->version})?", true)) {
            return self::SUCCESS;
        }

        try {
            $result = $updates->rollback($update);
            $this->info('Status: '.$result->status.' complete='.($result->rollback_complete ? 'yes' : 'partial'));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
