<?php

use App\Jobs\CrawlJobsJob;
use App\Jobs\GenerateDailyContentJob;
use App\Jobs\SendExamReminderJob;
use App\Jobs\SendSubscriptionExpiryNotification;
use App\Jobs\Seo\CheckBrokenLinksJob;
use App\Jobs\Seo\OptimizeLowScoreSeoJob;
use App\Jobs\Seo\PingSitemapJob;
use App\Jobs\Seo\RunSeoAuditJob;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Shared-hosting safe scheduler
|--------------------------------------------------------------------------
| Schedule::command() uses Symfony Process (proc_open). Many cPanel hosts
| disable proc_open, which floods SiteError with LogicException on every
| cron tick. Prefer Schedule::call + Artisan::call (in-process CallbackEvent).
|
| For CallbackEvent: call ->name() before ->withoutOverlapping().
*/

Schedule::call(fn () => Artisan::call('site:boost-performance'))
    ->name('site-boost-performance')
    ->hourly()
    ->when(fn () => Setting::getBool('performance_auto', false))
    ->withoutOverlapping(30);

Schedule::job(new CrawlJobsJob)->dailyAt('06:00')->when(
    fn () => (bool) config('aggregation.enable_legacy_crawl_jobs_schedule', false)
);
Schedule::job(new SendExamReminderJob)->dailyAt('08:00')->withoutOverlapping(55);
Schedule::job(new SendSubscriptionExpiryNotification)->dailyAt('09:00')->withoutOverlapping(55);

Schedule::call(fn () => Artisan::call('payments:expire-pending'))
    ->name('payments-expire-pending')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10);

Schedule::call(fn () => Artisan::call('wallet:reconcile'))
    ->name('wallet-reconcile')
    ->dailyAt('03:15')
    ->withoutOverlapping(30);

Schedule::call(fn () => Artisan::call('aggregation:notify-alerts'))
    ->name('aggregation-notify-alerts')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10);

Schedule::call(fn () => Artisan::call('jobs:expire'))
    ->name('jobs-expire')
    ->dailyAt('00:30')
    ->withoutOverlapping(10);

// Aggregation: check admin-configured times every minute; only dispatches queue jobs.
// Requires: * * * * * php artisan schedule:run
// Worker: php artisan queue:work --queue=crawlers,default (or auto via schedule below)
Schedule::call(fn () => Artisan::call('jobs:aggregate-dispatch'))
    ->name('jobs-aggregate-dispatch')
    ->everyMinute()
    ->withoutOverlapping(5);

// Process database queue on shared hosting (cPanel) without a long-lived worker.
Schedule::call(function () {
    Artisan::call('queue:work', [
        'connection' => 'database',
        '--queue' => 'crawlers,default',
        '--stop-when-empty' => true,
        '--max-time' => 55,
        '--tries' => 3,
    ]);
})
    ->name('queue-work-database')
    ->everyMinute()
    ->withoutOverlapping(55)
    ->when(fn () => config('queue.default') === 'database');

// Auto-heal: prune failed crawls, transient site errors, old failed jobs
Schedule::call(fn () => Artisan::call('site:auto-heal'))
    ->name('site-auto-heal')
    ->hourly()
    ->withoutOverlapping(30);

Schedule::call(fn () => Artisan::call('content:publish-scheduled'))
    ->name('content-publish-scheduled')
    ->everyFiveMinutes()
    ->when(fn () => (bool) config('content.enabled', false))
    ->withoutOverlapping(5);

// Automated employment content (template-based, no AI)
// Dispatches at most once per local day via unique job + cache lock inside service.
Schedule::call(function () {
    if (! config('content.enabled', false) || ! config('content.daily_generation_enabled', false)) {
        return;
    }
    $tz = (string) config('content.timezone', 'Asia/Tehran');
    $target = (string) config('content.daily_generation_time', '09:00');
    if (! preg_match('/^\d{2}:\d{2}$/', $target)) {
        return;
    }
    if (now($tz)->format('H:i') !== $target) {
        return;
    }
    $dayKey = now($tz)->toDateString();
    $lock = Cache::lock('content-schedule-dispatch-'.$dayKey, 90);
    if (! $lock->get()) {
        return;
    }
    try {
        GenerateDailyContentJob::dispatch();
    } finally {
        // Keep lock briefly so overlapping schedule ticks in the same minute do not re-dispatch.
        // Unique job covers longer windows; lock covers the tick burst.
    }
})->name('content-generate-daily')->everyMinute()->withoutOverlapping(5);

// SEO scheduled tasks
Schedule::job(new CheckBrokenLinksJob)->dailyAt('03:00')->withoutOverlapping(30);
Schedule::job(new RunSeoAuditJob('full'))->weeklyOn(1, '04:00')->withoutOverlapping(60);
Schedule::job(new OptimizeLowScoreSeoJob)->dailyAt('05:00')->withoutOverlapping(60);
Schedule::job(new PingSitemapJob)->dailyAt('06:00')->withoutOverlapping(30);

// Application ZIP backup (DB + private + public). Requires schedule:run cron.
Schedule::call(function () {
    $code = Artisan::call('backup:run');
    $output = trim(Artisan::output());
    if ($output !== '') {
        Log::channel('single')->info('backup:run schedule', ['exit' => $code, 'output' => $output]);
        @file_put_contents(
            storage_path('logs/backup-schedule.log'),
            '['.now()->toDateTimeString()."] exit={$code}\n{$output}\n",
            FILE_APPEND
        );
    }
})
    ->name('backup-run')
    ->dailyAt('03:15')
    ->withoutOverlapping(120);
