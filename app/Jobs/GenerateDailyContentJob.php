<?php

namespace App\Jobs;

use App\Services\Content\ContentGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateDailyContentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct()
    {
        $this->onQueue((string) config('content.queue', 'default'));
    }

    public function uniqueId(): string
    {
        return 'content-generate-daily-'.now(config('content.timezone', 'Asia/Tehran'))->toDateString();
    }

    public function handle(ContentGeneratorService $generator): void
    {
        $stats = $generator->generateDaily();
        Log::info('GenerateDailyContentJob finished', [
            'created' => $stats['created'],
            'updated' => $stats['updated'],
            'skipped' => $stats['skipped'],
            'failed' => $stats['failed'],
            'published' => $stats['published'],
            'error_count' => count($stats['errors']),
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('GenerateDailyContentJob failed permanently', [
            'message' => $e?->getMessage(),
        ]);
    }
}
