<?php

namespace Database\Factories;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Models\CrawlerRun;
use App\Models\JobSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrawlerRun>
 */
class CrawlerRunFactory extends Factory
{
    protected $model = CrawlerRun::class;

    public function definition(): array
    {
        return [
            'job_source_id' => JobSource::factory(),
            'status' => CrawlerRunStatus::Pending,
            'started_at' => null,
            'finished_at' => null,
            'jobs_found' => 0,
            'jobs_created' => 0,
            'jobs_updated' => 0,
            'duplicates' => 0,
            'errors_count' => 0,
            'execution_ms' => null,
            'meta' => [],
        ];
    }
}
