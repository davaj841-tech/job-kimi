<?php

namespace App\Console\Production;

use Illuminate\Console\Command;

/**
 * Registered only when nunomaduro/collision (require-dev) is absent.
 * Prevents "There are no commands defined in the test namespace." on production
 * composer install --no-dev when something still invokes `php artisan test`.
 */
class ArtisanTestStubCommand extends Command
{
    protected $signature = 'test
        {--without-tty : Ignored (stub)}
        {--compact : Ignored (stub)}
        {--coverage : Ignored (stub)}
        {--min= : Ignored (stub)}
        {--p|parallel : Ignored (stub)}
        {--profile : Ignored (stub)}
        {--recreate-databases : Ignored (stub)}
        {--drop-databases : Ignored (stub)}
        {--without-databases : Ignored (stub)}
        {--without-cache : Ignored (stub)}
    ';

    protected $description = 'Application tests (unavailable without composer dev dependencies)';

    public function handle(): int
    {
        $this->error('php artisan test is not available in this environment.');
        $this->line('PHPUnit/Collision are require-dev packages and are omitted by composer install --no-dev.');
        $this->line('Run tests only on CI/dev with a full composer install (including --dev).');

        return self::FAILURE;
    }
}
