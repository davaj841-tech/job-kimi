<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Services\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CrawlJobsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $sourceUrls
     * @param  array<int, string>  $keywords
     */
    public function __construct(
        public array $sourceUrls = [],
        public array $keywords = []
    ) {}

    public function handle(AIService $aiService): void
    {
        $urls = $this->sourceUrls;
        if ($urls === []) {
            $stored = Setting::get('ai_job_crawl_sources', '[]');
            $decoded = is_string($stored) ? json_decode($stored, true) : $stored;
            $urls = is_array($decoded) ? $decoded : [];
        }

        if ($urls === []) {
            Log::info('CrawlJobsJob: no source URLs configured.');

            return;
        }

        $jobs = $aiService->crawlJobs($urls, $this->keywords);

        Log::info('CrawlJobsJob completed', ['extracted' => count($jobs)]);
    }
}
