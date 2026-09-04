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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds official employment sources from config/aggregation.php.
 * Idempotent by slug (updateOrCreate). Does not delete existing sources
 * or invent job posts. Endpoints not in config are disabled, not deleted.
 *
 * priority: crawl dispatch sort (lower = earlier). Original column is
 * UNSIGNED TINYINT (0–255). Config may list values >255; those are clamped
 * to the live column max so MySQL seed never fails with SQLSTATE[22003].
 */
class PilotJobSourceSeeder extends Seeder
{
    /** Original / production-safe ceiling for unsignedTinyInteger. */
    public const PRIORITY_MIN = 0;

    public const PRIORITY_TINYINT_MAX = 255;

    /** After optional widen migration (SMALLINT UNSIGNED). */
    public const PRIORITY_SMALLINT_MAX = 65535;

    /** Default when config omits priority. */
    public const PRIORITY_DEFAULT = 50;

    private ?int $priorityMax = null;

    public function run(): void
    {
        $sources = config('aggregation.official_sources');
        if (! is_array($sources) || $sources === []) {
            $sources = config('aggregation.pilot_sources', []);
        }

        $priorityMax = $this->priorityColumnMax();

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
                    'priority' => $this->normalizePriority((int) ($pilot['priority'] ?? self::PRIORITY_DEFAULT), $priorityMax),
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

    public function normalizePriority(int $priority, ?int $max = null): int
    {
        $max ??= $this->priorityColumnMax();

        return max(self::PRIORITY_MIN, min($max, $priority));
    }

    /**
     * Detect live MySQL/MariaDB column capacity; fall back to TINYINT max
     * unless the widen migration has already been applied.
     */
    public function priorityColumnMax(): int
    {
        if ($this->priorityMax !== null) {
            return $this->priorityMax;
        }

        $this->priorityMax = self::PRIORITY_TINYINT_MAX;

        try {
            if ($this->priorityColumnWasWidened()) {
                $this->priorityMax = self::PRIORITY_SMALLINT_MAX;

                return $this->priorityMax;
            }

            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'], true) && Schema::hasTable('job_sources')) {
                $row = DB::selectOne('SHOW COLUMNS FROM `job_sources` LIKE ?', ['priority']);
                $type = strtolower((string) ($row->Type ?? ''));
                if (str_contains($type, 'smallint')) {
                    $this->priorityMax = self::PRIORITY_SMALLINT_MAX;
                } elseif (str_contains($type, 'mediumint') || preg_match('/\bint\b/', $type)) {
                    $this->priorityMax = self::PRIORITY_SMALLINT_MAX;
                } else {
                    $this->priorityMax = self::PRIORITY_TINYINT_MAX;
                }
            }
        } catch (\Throwable) {
            $this->priorityMax = self::PRIORITY_TINYINT_MAX;
        }

        return $this->priorityMax;
    }

    private function priorityColumnWasWidened(): bool
    {
        if (! Schema::hasTable('migrations')) {
            return false;
        }

        return DB::table('migrations')
            ->where('migration', 'like', '%widen_job_sources_priority%')
            ->exists();
    }
}
