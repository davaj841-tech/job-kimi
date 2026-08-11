<?php

use App\Jobs\CrawlJobsJob;
use App\Jobs\SendExamReminderJob;
use App\Jobs\SendSubscriptionExpiryNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new CrawlJobsJob)->dailyAt('06:00')->when(
    fn () => (bool) config('aggregation.enable_legacy_crawl_jobs_schedule', false)
);
Schedule::job(new SendExamReminderJob)->dailyAt('08:00');
Schedule::job(new SendSubscriptionExpiryNotification)->dailyAt('09:00');
Schedule::command('backup:run')->dailyAt('03:00');

// Aggregation: check admin-configured times every minute; only dispatches queue jobs.
// Never crawls HTTP inline. Requires: * * * * * php artisan schedule:run
// Worker: php artisan queue:work --queue=crawlers,default
Schedule::command('jobs:aggregate-dispatch')
    ->everyMinute()
    ->withoutOverlapping(5);

// Auto-heal: prune failed crawls, transient site errors, old failed jobs
Schedule::command('site:auto-heal')
    ->hourly()
    ->withoutOverlapping(30);

Schedule::command('content:publish-scheduled')
    ->everyFiveMinutes()
    ->when(fn () => (bool) config('content.enabled', false))
    ->withoutOverlapping(5)
    ->name('content-publish-scheduled');

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
    $lock = \Illuminate\Support\Facades\Cache::lock('content-schedule-dispatch-'.$dayKey, 90);
    if (! $lock->get()) {
        return;
    }
    try {
        \App\Jobs\GenerateDailyContentJob::dispatch();
    } finally {
        // Keep lock briefly so overlapping schedule ticks in the same minute do not re-dispatch.
        // Unique job covers longer windows; lock covers the tick burst.
    }
})->everyMinute()->name('content-generate-daily')->withoutOverlapping(5);
