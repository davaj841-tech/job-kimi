<?php

namespace App\Console\Commands;

use App\Services\SiteAutoHealService;
use Illuminate\Console\Command;

class SiteAutoHealCommand extends Command
{
    protected $signature = 'site:auto-heal
                            {--aggressive : حذف فوری همه وضعیت‌های ناموفق خزش و failed jobs}';

    protected $description = 'پاکسازی خودکار خطاها، اجراهای ناموفق خزش و سبک‌سازی پنل';

    public function handle(SiteAutoHealService $heal): int
    {
        $stats = $heal->run((bool) $this->option('aggressive'));
        foreach ($stats as $key => $value) {
            $this->line("{$key}: {$value}");
        }
        $this->info('خودترمیمی انجام شد.');

        return self::SUCCESS;
    }
}
