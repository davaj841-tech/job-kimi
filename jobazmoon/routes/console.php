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

Schedule::job(new CrawlJobsJob())->dailyAt('06:00');
Schedule::job(new SendExamReminderJob())->dailyAt('08:00');
Schedule::job(new SendSubscriptionExpiryNotification())->dailyAt('09:00');
Schedule::command('backup:run')->dailyAt('03:00');
