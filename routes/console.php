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

Schedule::job(new CrawlJobsJob())->dailyAt('06:00')->when(
    fn () => (bool) config('aggregation.enable_legacy_crawl_jobs_schedule', false)
);
Schedule::job(new SendExamReminderJob())->dailyAt('08:00');
Schedule::job(new SendSubscriptionExpiryNotification())->dailyAt('09:00');
Schedule::command('backup:run')->dailyAt('03:00');

// Aggregation: check admin-configured times every minute; only dispatches queue jobs.
// Never crawls HTTP inline. Requires: * * * * * php artisan schedule:run
// Worker: php artisan queue:work --queue=crawlers,default
Schedule::command('jobs:aggregate-dispatch')
    ->everyMinute()
    ->withoutOverlapping(5);
