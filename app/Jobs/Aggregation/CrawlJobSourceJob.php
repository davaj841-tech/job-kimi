<?php

namespace App\Jobs\Aggregation;

use App\Models\JobSource;
use App\Services\Aggregation\AggregationScheduleService;
use App\Services\Aggregation\CrawlOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued crawl for a single whitelisted source.
 * Unique per source to prevent overlapping crawls of the same source.
 */
class CrawlJobSourceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    /** Keep uniqueness for the duration of a typical crawl window. */
    public int $uniqueFor = 900;

    public function __construct(
        public int $jobSourceId
    ) {
        $this->onQueue(config('aggregation.queue', 'crawlers'));

        try {
            $tries = (int) app(AggregationScheduleService::class)->get()['retry_tries'];
            if ($tries >= 1) {
                $this->tries = $tries;
            }
        } catch (\Throwable) {
            // Keep default tries when settings are unavailable.
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->jobSourceId;
    }

    public function handle(CrawlOrchestrator $orchestrator): void
    {
        $source = JobSource::query()->with('endpoints')->find($this->jobSourceId);
        if (! $source) {
            Log::warning('CrawlJobSourceJob: source missing', ['id' => $this->jobSourceId]);

            return;
        }

        if (! $source->is_enabled || ! $source->is_approved) {
            Log::info('CrawlJobSourceJob skipped non-whitelisted source', ['id' => $source->id]);

            return;
        }

        $result = $orchestrator->crawlSource($source);
        Log::info('CrawlJobSourceJob finished', $result['summary']);
    }
}
