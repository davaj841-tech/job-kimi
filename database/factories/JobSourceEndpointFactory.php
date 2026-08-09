<?php

namespace Database\Factories;

use App\Enums\Aggregation\JobEndpointType;
use App\Models\JobSource;
use App\Models\JobSourceEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobSourceEndpoint>
 */
class JobSourceEndpointFactory extends Factory
{
    protected $model = JobSourceEndpoint::class;

    public function definition(): array
    {
        return [
            'job_source_id' => JobSource::factory(),
            'url' => 'https://careers.example.gov.ir/feed',
            'endpoint_type' => JobEndpointType::Rss,
            'http_method' => 'GET',
            'parser_type' => 'rss_default',
            'is_enabled' => true,
            'sort_order' => 0,
        ];
    }
}
