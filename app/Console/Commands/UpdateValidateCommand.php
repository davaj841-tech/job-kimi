<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Update\UpdateManager;
use Illuminate\Console\Command;
use Throwable;

class UpdateValidateCommand extends Command
{
    protected $signature = 'update:validate {zip : Path to update ZIP}';

    protected $description = 'Validate an update pack without installing it';

    public function handle(UpdateManager $updates): int
    {
        $zip = $this->argument('zip');
        if (! is_file($zip)) {
            $this->error('ZIP not found: '.$zip);

            return self::FAILURE;
        }

        try {
            $result = $updates->validatePack($zip);
            $this->info('OK — target '.$result['target_version'].' (from '.$result['current_version'].')');
            /** @var array<string, bool|string> $preflight */
            $preflight = is_array($result['preflight'] ?? null) ? $result['preflight'] : [];
            $rows = [];
            foreach ($preflight as $k => $v) {
                $rows[] = [(string) $k, $v ? 'pass' : 'fail'];
            }
            $this->table(['Check', 'Status'], $rows);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
