<?php

namespace App\Console\Commands;

use App\Models\JobSource;
use App\Services\Aggregation\CrawlOrchestrator;
use Database\Seeders\PilotJobSourceSeeder;
use Illuminate\Console\Command;

/**
 * Seed + crawl ایران‌استخدام / ای‌استخدام board sources (government/reputable filter).
 */
class SyncBoardJobListingsCommand extends Command
{
    protected $signature = 'jobs:sync-boards
                            {--seed : Upsert board sources from config before crawl}
                            {--slug= : Only one board slug (iranestekhdam|e-estekhdam)}';

    protected $description = 'دریافت آگهی‌های دولتی/معتبر از ایران‌استخدام و ای‌استخدام و ثبت به‌صورت در انتظار بررسی';

    public function handle(CrawlOrchestrator $orchestrator): int
    {
        if ($this->option('seed')) {
            (new PilotJobSourceSeeder)->run();
            $this->info('منابع از config به‌روز شدند.');
        }

        $slugs = $this->option('slug')
            ? [(string) $this->option('slug')]
            : ['iranestekhdam', 'e-estekhdam'];

        $sources = JobSource::query()
            ->whereIn('slug', $slugs)
            ->with('endpoints')
            ->orderBy('priority')
            ->get();

        if ($sources->isEmpty()) {
            $this->warn('منبع یافت نشد. با --seed دوباره اجرا کنید.');

            return self::FAILURE;
        }

        foreach ($sources as $source) {
            $this->line("در حال دریافت: {$source->slug} …");
            try {
                $result = $orchestrator->crawlSource($source);
                $summary = $result['summary'] ?? [];
                $this->info(sprintf(
                    '  %s — پیدا شده: %s | جدید: %s | به‌روز: %s | رد: %s',
                    $source->slug,
                    $summary['found'] ?? 0,
                    $summary['created'] ?? 0,
                    $summary['updated'] ?? 0,
                    $summary['rejected'] ?? 0
                ));
            } catch (\Throwable $e) {
                $this->error("  خطا در {$source->slug}: ".$e->getMessage());
            }
        }

        $this->comment('آگهی‌ها با وضعیت pending ثبت می‌شوند؛ از پنل استخدام‌ها تأیید کنید.');

        return self::SUCCESS;
    }
}
