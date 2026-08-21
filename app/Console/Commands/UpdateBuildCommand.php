<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Update\SemVer;
use App\Services\Update\UpdatePackBuilder;
use Illuminate\Console\Command;
use Throwable;

class UpdateBuildCommand extends Command
{
    protected $signature = 'update:build
                            {version : Target SemVer e.g. 1.0.1}
                            {--from= : Previous version (default: current)}
                            {--file=* : Explicit relative file paths to include}
                            {--delete=* : Relative paths to delete on target}
                            {--description= : Release notes}
                            {--type=patch : Release type patch|minor|major|hotfix}
                            {--no-maintenance : Disable maintenance_mode in manifest}';

    protected $description = 'Build a jobazmoon-update-vX.Y.Z.zip pack';

    public function handle(UpdatePackBuilder $builder): int
    {
        $version = (string) $this->argument('version');
        $files = $this->option('file');
        $files = is_array($files) && $files !== [] ? array_values($files) : null;

        try {
            $path = $builder->build(
                targetVersion: $version,
                previousVersion: $this->option('from') ?: SemVer::current(),
                files: $files,
                deleted: array_values((array) $this->option('delete')),
                description: (string) ($this->option('description') ?: ''),
                releaseType: (string) ($this->option('type') ?: 'patch'),
                maintenanceMode: ! (bool) $this->option('no-maintenance'),
            );
            $this->info('Built: '.$path);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
