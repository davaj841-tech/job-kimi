<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\Admin\PerformanceAdminController;
use Illuminate\Console\Command;

class BoostSitePerformance extends Command
{
    protected $signature = 'site:boost-performance';

    protected $description = 'Warm caches and optimize the site';

    public function handle(): int
    {
        app(PerformanceAdminController::class)->boost();
        $this->info('Site performance boost completed.');

        return self::SUCCESS;
    }
}
