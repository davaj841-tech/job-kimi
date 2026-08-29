<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Feature;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Models\Setting;
use App\Services\Aggregation\AggregationScheduleService;
use App\Services\FeatureFlagService;
use Illuminate\Contracts\Console\Kernel;

$schedule = app(AggregationScheduleService::class);
$features = app(FeatureFlagService::class);

echo 'queue='.config('queue.default').PHP_EOL;
echo 'job-crawler='.($features->isEnabled('job-crawler', true) ? 'on' : 'off').PHP_EOL;
echo 'schedule_enabled='.($schedule->get()['enabled'] ? 'yes' : 'no').PHP_EOL;
echo 'current_slot='.$schedule->nowSlot().PHP_EOL;
echo 'is_due_now='.($schedule->isDueNow() ? 'yes' : 'no').PHP_EOL;
echo 'enabled_times='.implode(',', $schedule->enabledGlobalTimes()).PHP_EOL;
echo 'sources_total='.JobSource::count().PHP_EOL;
echo 'sources_dispatchable='.JobSource::dispatchable()->count().PHP_EOL;
echo 'sources_whitelisted='.JobSource::whitelisted()->count().PHP_EOL;
echo 'aggregated_jobs='.JobPost::whereNotNull('job_source_id')->count().PHP_EOL;
echo 'pending_aggregated='.JobPost::whereNotNull('job_source_id')->where('status', 'pending')->count().PHP_EOL;

JobSource::query()->get(['id', 'name', 'is_enabled', 'is_approved', 'quality_status', 'crawl_frequency'])->each(function ($s) {
    echo "source #{$s->id} {$s->name} en={$s->is_enabled} ap={$s->is_approved} q={$s->quality_status?->value} freq={$s->crawl_frequency}".PHP_EOL;
});

Feature::query()->where('name', 'job-crawler')->get()->each(function ($f) {
    echo "feature {$f->name} enabled={$f->enabled}".PHP_EOL;
});

$raw = Setting::get('aggregation_schedule');
echo 'schedule_raw='.substr(is_string($raw) ? $raw : json_encode($raw), 0, 300).PHP_EOL;
