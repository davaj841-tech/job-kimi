<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SystemUpdate;
use Illuminate\Console\Command;

class UpdateHistoryCommand extends Command
{
    protected $signature = 'update:history {--limit=20}';

    protected $description = 'List recent system updates';

    public function handle(): int
    {
        $rows = SystemUpdate::query()
            ->latest('id')
            ->limit((int) $this->option('limit'))
            ->get(['id', 'version', 'previous_version', 'status', 'started_at', 'duration_ms', 'error']);

        if ($rows->isEmpty()) {
            $this->warn('No updates recorded.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Version', 'From', 'Status', 'Started', 'ms', 'Error'],
            $rows->map(fn (SystemUpdate $u) => [
                $u->id,
                $u->version,
                $u->previous_version,
                $u->status,
                optional($u->started_at)->toDateTimeString(),
                $u->duration_ms,
                $u->error ? mb_substr($u->error, 0, 40) : '',
            ])->all()
        );

        return self::SUCCESS;
    }
}
