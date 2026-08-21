<?php

namespace App\Console\Commands;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Jobs\Aggregation\CrawlJobSourceJob;
use App\Models\CrawlerRun;
use App\Models\JobSource;
use App\Services\Aggregation\AggregationScheduleService;
use App\Services\Aggregation\JobSourceManager;
use App\Services\FeatureFlagService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dispatch crawl jobs for enabled + approved sources.
 * Scheduled ticks check admin-configured times; heavy work stays on the crawlers queue.
 */
class AggregateJobsDispatchCommand extends Command
{
    protected $signature = 'jobs:aggregate-dispatch
                            {--dry-run : List due sources without queueing crawl jobs}
                            {--sync : Run crawls inline instead of queueing}
                            {--force : Ignore schedule window (still respects whitelist + frequency)}';

    protected $description = 'Dispatch aggregation crawl jobs for administrator-whitelisted job sources';

    public function handle(JobSourceManager $sources, AggregationScheduleService $schedule, FeatureFlagService $features): int
    {
        if (! $features->isEnabled('job-crawler', true)) {
            $this->warn('Feature flag job-crawler is disabled.');

            return self::SUCCESS;
        }

        $config = $schedule->get();
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $sync = (bool) $this->option('sync');

        if (! $force && ! $config['enabled']) {
            $this->warn('Aggregation schedule is disabled. Use --force to dispatch manually.');

            return self::SUCCESS;
        }

        if (! $force && ! $schedule->isDueNow()) {
            if ($this->output->isVerbose()) {
                $this->line('Not a scheduled slot ('.$schedule->nowSlot().' '.$config['timezone'].').');
            }

            return self::SUCCESS;
        }

        $slot = $schedule->nowSlot();

        if (! $force && ! $dryRun) {
            $lock = Cache::lock('aggregation.dispatch.slot.'.$slot, 55);
            if (! $lock->get()) {
                $this->warn("Slot {$slot} already dispatched recently. Skipping.");

                return self::SUCCESS;
            }
        }

        $candidates = $force
            ? $sources->dispatchableSourcesDueByFrequency()
            : $sources->dispatchableSourcesForSlot($slot);

        if ($candidates->isEmpty()) {
            $this->warn('No due enabled+approved sources for this tick.');

            return self::SUCCESS;
        }

        $this->info('Due sources: '.$candidates->count().' (slot '.$slot.', tz '.$config['timezone'].')');
        $this->newLine();

        $this->table(
            ['ID', 'Name', 'Domain', 'Schedule', 'Frequency', 'Min interval', 'Last crawl'],
            $candidates->map(function (JobSource $source) use ($schedule) {
                return [
                    $source->id,
                    $source->name,
                    $source->domain,
                    $source->schedule_mode ?: 'global',
                    $source->crawl_frequency,
                    $schedule->minimumIntervalMinutes($source).'m',
                    ($source->last_crawled_at instanceof \Carbon\CarbonInterface ? $source->last_crawled_at->toDateTimeString() : null) ?? 'never',
                ];
            })->all()
        );

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry-run only — no jobs queued.');
            $this->line('Queue: '.config('aggregation.queue', 'crawlers'));
            $this->line('Allowed domains: '.implode(', ', $sources->allowedDomains()));

            return self::SUCCESS;
        }

        $queue = config('aggregation.queue', 'crawlers');
        $maxConcurrent = (int) $config['max_concurrent'];
        $delaySeconds = (int) $config['dispatch_delay_seconds'];
        $dispatched = 0;
        $skippedBusy = 0;
        $offsetDelay = 0;

        foreach ($candidates as $source) {
            if ($this->isSourceBusy($source->id)) {
                $this->line("Skip busy #{$source->id} {$source->name}");
                $skippedBusy++;

                continue;
            }

            if (! $sync) {
                $inflight = $this->inflightCrawlerJobsCount($queue);
                if ($inflight + $dispatched >= $maxConcurrent) {
                    $this->warn("Concurrency limit ({$maxConcurrent}) reached. Remaining sources skipped this tick.");
                    break;
                }
            }

            if ($sync) {
                CrawlJobSourceJob::dispatchSync($source->id);
                $this->line("Crawled sync: #{$source->id} {$source->name}");
            } else {
                $job = new CrawlJobSourceJob($source->id);
                if ($delaySeconds > 0 && $offsetDelay > 0) {
                    dispatch($job)->delay(now()->addSeconds($offsetDelay));
                } else {
                    dispatch($job);
                }
                $this->line("Queued on [{$queue}]: #{$source->id} {$source->name}");
                $offsetDelay += $delaySeconds;
            }

            $dispatched++;
        }

        $this->newLine();
        $this->info($sync
            ? "Inline crawl finished ({$dispatched})."
            : "Dispatched {$dispatched} job(s); skipped busy {$skippedBusy}. Process with: php artisan queue:work --queue={$queue},default");

        return self::SUCCESS;
    }

    protected function isSourceBusy(int $sourceId): bool
    {
        // Ignore stuck "running" rows older than TTL so a crashed worker cannot block forever.
        $ttlMinutes = max(5, (int) config('aggregation.stuck_run_minutes', 30));
        $freshAfter = now()->subMinutes($ttlMinutes);

        return CrawlerRun::query()
            ->where('job_source_id', $sourceId)
            ->where('status', CrawlerRunStatus::Running)
            ->where(function ($q) use ($freshAfter) {
                $q->whereNull('started_at')
                    ->orWhere('started_at', '>=', $freshAfter);
            })
            ->exists();
    }

    protected function inflightCrawlerJobsCount(string $queue): int
    {
        $running = CrawlerRun::query()->where('status', CrawlerRunStatus::Running)->count();

        try {
            $queued = (int) DB::table('jobs')->where('queue', $queue)->count();
        } catch (\Throwable) {
            $queued = 0;
        }

        return $running + $queued;
    }
}
