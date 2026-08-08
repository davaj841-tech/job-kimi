<?php

namespace Database\Seeders;

use App\Enums\Aggregation\JobCrawlerType;
use App\Enums\Aggregation\JobEndpointType;
use App\Enums\Aggregation\JobSourceQualityStatus;
use App\Enums\Aggregation\JobSourceReliability;
use App\Enums\Aggregation\JobSourceType;
use App\Models\JobSource;
use App\Models\JobSourceEndpoint;
use Illuminate\Database\Seeder;

/**
 * Seeds official employment sources from config/aggregation.php.
 * Idempotent by slug. Does not invent job posts.
 */
class PilotJobSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = config('aggregation.official_sources');
        if (! is_array($sources) || $sources === []) {
            $sources = config('aggregation.pilot_sources', []);
        }

        foreach ($sources as $pilot) {
            if (! is_array($pilot) || empty($pilot['slug'])) {
                continue;
            }

            $quality = $pilot['quality_status'] ?? JobSourceQualityStatus::Active->value;

            $source = JobSource::query()->updateOrCreate(
                ['slug' => $pilot['slug']],
                [
                    'name' => $pilot['name'],
                    'official_url' => $pilot['official_url'],
                    'domain' => $pilot['domain'] ?? JobSource::extractDomain($pilot['official_url'] ?? null),
                    'source_type' => JobSourceType::from($pilot['source_type']),
                    'reliability_level' => JobSourceReliability::from($pilot['reliability_level']),
                    'priority' => (int) ($pilot['priority'] ?? 50),
                    'is_enabled' => (bool) ($pilot['is_enabled'] ?? true),
                    'is_approved' => (bool) ($pilot['is_approved'] ?? true),
                    'quality_status' => JobSourceQualityStatus::from($quality),
                    'crawler_type' => JobCrawlerType::from($pilot['crawler_type']),
                    'crawl_frequency' => $pilot['crawl_frequency'] ?? 'daily',
                    'schedule_mode' => $pilot['schedule_mode'] ?? 'global',
                    'custom_schedule_times' => $pilot['custom_schedule_times'] ?? null,
                    'notes' => $pilot['notes'] ?? null,
                    'quality_notes' => $pilot['quality_notes'] ?? null,
                ]
            );

            foreach ($pilot['endpoints'] ?? [] as $index => $endpoint) {
                if (! is_array($endpoint) || empty($endpoint['url'])) {
                    continue;
                }

                JobSourceEndpoint::query()->updateOrCreate(
                    [
                        'job_source_id' => $source->id,
                        'url' => $endpoint['url'],
                    ],
                    [
                        'endpoint_type' => JobEndpointType::from($endpoint['endpoint_type'] ?? 'html'),
                        'http_method' => strtoupper((string) ($endpoint['http_method'] ?? 'GET')),
                        'parser_type' => $endpoint['parser_type'] ?? null,
                        'is_enabled' => (bool) ($endpoint['is_enabled'] ?? true),
                        'sort_order' => (int) ($endpoint['sort_order'] ?? $index),
                    ]
                );
            }

            $keepUrls = collect($pilot['endpoints'] ?? [])
                ->pluck('url')
                ->filter()
                ->values()
                ->all();

            if ($keepUrls !== []) {
                $source->endpoints()
                    ->whereNotIn('url', $keepUrls)
                    ->update(['is_enabled' => false]);
            }
        }
    }
}
