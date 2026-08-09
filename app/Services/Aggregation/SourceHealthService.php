<?php

namespace App\Services\Aggregation;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Enums\Aggregation\JobSourceQualityStatus;
use App\Models\CrawlerRun;
use App\Models\JobSource;
use Illuminate\Support\Carbon;

/**
 * Records per-source crawl health and applies safe quality transitions.
 * Never auto-approves. Never changes manual_only without admin action.
 */
class SourceHealthService
{
    public const OUTCOME_SUCCESS = 'success';

    public const OUTCOME_EMPTY_SUCCESS = 'empty_success';

    public const OUTCOME_HTTP_FAILURE = 'http_failure';

    public const OUTCOME_QUALITY_FAILURE = 'quality_failure';

    public const OUTCOME_PARTIAL = 'partial';

    /**
     * @param  array<string, mixed>  $summary  Orchestrator summary
     * @return array{outcome: string, quality_changed: bool, previous_quality: ?string, quality_status: string, backoff_until: ?string}
     */
    public function recordCrawl(JobSource $source, CrawlerRun $run, array $summary, bool $isManualTest = false): array
    {
        $source->refresh();
        $previousQuality = $source->quality_status?->value;
        $outcome = $this->classifyOutcome($run, $summary);
        $httpStatus = $this->normalizeHttpStatus($summary['http_status'] ?? null);

        $updates = [
            'last_http_status' => $httpStatus,
            'last_crawl_outcome' => $outcome,
            'lifetime_jobs_found' => (int) $source->lifetime_jobs_found + (int) ($summary['found'] ?? 0),
            'lifetime_jobs_created' => (int) $source->lifetime_jobs_created + (int) ($summary['created'] ?? 0),
            'lifetime_jobs_updated' => (int) $source->lifetime_jobs_updated + (int) ($summary['updated'] ?? 0),
            'lifetime_duplicates' => (int) $source->lifetime_duplicates + (int) ($summary['duplicates'] ?? 0),
            'lifetime_rejected' => (int) $source->lifetime_rejected + (int) ($summary['rejected'] ?? 0),
            'lifetime_validation_errors' => (int) $source->lifetime_validation_errors + (int) ($summary['validation_errors'] ?? 0),
        ];

        $newQuality = $source->quality_status ?? JobSourceQualityStatus::Active;

        if ($outcome === self::OUTCOME_HTTP_FAILURE) {
            $failures = (int) $source->consecutive_failures + 1;
            $updates['consecutive_failures'] = $failures;
            $updates['consecutive_empty_crawls'] = 0;
            $updates['total_failed_crawls'] = (int) $source->total_failed_crawls + 1;
            $updates['health_backoff_until'] = $this->computeBackoffUntil($failures);
            $newQuality = $this->qualityAfterHttpFailure($source, $failures);
        } elseif ($outcome === self::OUTCOME_EMPTY_SUCCESS) {
            $empties = (int) $source->consecutive_empty_crawls + 1;
            $updates['consecutive_failures'] = 0;
            $updates['consecutive_empty_crawls'] = $empties;
            $updates['total_successful_crawls'] = (int) $source->total_successful_crawls + 1;
            $updates['total_empty_successful_crawls'] = (int) $source->total_empty_successful_crawls + 1;
            $updates['health_backoff_until'] = null;
            $newQuality = $this->qualityAfterEmptySuccess($source, $isManualTest);
        } elseif ($outcome === self::OUTCOME_QUALITY_FAILURE) {
            $updates['consecutive_failures'] = 0;
            $updates['consecutive_empty_crawls'] = 0;
            $updates['total_failed_crawls'] = (int) $source->total_failed_crawls + 1;
            $updates['health_backoff_until'] = null;
            $newQuality = $this->qualityAfterHighRejection($source);
        } else {
            // success or partial with usable data
            $updates['consecutive_failures'] = 0;
            $updates['consecutive_empty_crawls'] = 0;
            $updates['total_successful_crawls'] = (int) $source->total_successful_crawls + 1;
            $updates['health_backoff_until'] = null;
            $newQuality = $this->qualityAfterSuccessWithData($source, $summary, $isManualTest);
        }

        if ($newQuality !== ($source->quality_status ?? null)) {
            $updates['quality_status'] = $newQuality;
            $updates['quality_notes'] = $this->appendQualityNote(
                $source->quality_notes,
                sprintf(
                    'Health auto: %s → %s (%s, http=%s)',
                    $previousQuality ?? 'null',
                    $newQuality->value,
                    $outcome,
                    $httpStatus ?? '-'
                )
            );
        }

        $source->update($updates);
        $source->refresh();

        return [
            'outcome' => $outcome,
            'quality_changed' => $previousQuality !== $source->quality_status?->value,
            'previous_quality' => $previousQuality,
            'quality_status' => $source->quality_status?->value ?? JobSourceQualityStatus::Active->value,
            'backoff_until' => $source->health_backoff_until?->toIso8601String(),
        ];
    }

    /**
     * Admin-only counter reset. Does not change approval or quality unless requested.
     */
    public function resetCounters(JobSource $source, bool $clearBackoff = true): JobSource
    {
        $payload = [
            'consecutive_failures' => 0,
            'consecutive_empty_crawls' => 0,
        ];
        if ($clearBackoff) {
            $payload['health_backoff_until'] = null;
        }
        $source->update($payload);

        return $source->fresh();
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function classifyOutcome(CrawlerRun $run, array $summary): string
    {
        $status = $run->status;
        $found = (int) ($summary['found'] ?? $run->jobs_found ?? 0);
        $created = (int) ($summary['created'] ?? $run->jobs_created ?? 0);
        $updated = (int) ($summary['updated'] ?? $run->jobs_updated ?? 0);
        $rejected = (int) ($summary['rejected'] ?? 0);
        $http = $this->normalizeHttpStatus($summary['http_status'] ?? null);

        if ($status === CrawlerRunStatus::Completed && $found === 0) {
            return self::OUTCOME_EMPTY_SUCCESS;
        }

        if ($status === CrawlerRunStatus::Partial) {
            return self::OUTCOME_PARTIAL;
        }

        if ($status === CrawlerRunStatus::Completed && $found > 0) {
            return self::OUTCOME_SUCCESS;
        }

        // Failed run: distinguish HTTP/transport vs all-rejected quality failure.
        if ($found > 0 && $created === 0 && $updated === 0 && $rejected > 0) {
            return self::OUTCOME_QUALITY_FAILURE;
        }

        if ($this->isHttpFailureStatus($http) || $found === 0) {
            return self::OUTCOME_HTTP_FAILURE;
        }

        return self::OUTCOME_HTTP_FAILURE;
    }

    public function isInBackoff(JobSource $source): bool
    {
        return $source->health_backoff_until !== null
            && $source->health_backoff_until->isFuture();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildAlerts(): array
    {
        $threshold = (int) config('aggregation.health.consecutive_failure_threshold', 3);
        $emptyWarn = (int) config('aggregation.health.consecutive_empty_warning', 5);
        $staleDays = (int) config('aggregation.health.stale_success_days', 7);
        $alerts = [];

        JobSource::query()
            ->where('is_approved', true)
            ->orderBy('priority')
            ->get()
            ->each(function (JobSource $source) use (&$alerts, $threshold, $emptyWarn, $staleDays) {
                if ((int) $source->consecutive_failures >= $threshold) {
                    $alerts[] = [
                        'type' => 'repeated_failures',
                        'severity' => 'high',
                        'job_source_id' => $source->id,
                        'slug' => $source->slug,
                        'name' => $source->name,
                        'message' => "شکست‌های متوالی: {$source->consecutive_failures}",
                    ];
                }
                if ((int) $source->consecutive_empty_crawls >= $emptyWarn) {
                    $alerts[] = [
                        'type' => 'repeated_empty',
                        'severity' => 'medium',
                        'job_source_id' => $source->id,
                        'slug' => $source->slug,
                        'name' => $source->name,
                        'message' => "خزش موفق خالی متوالی: {$source->consecutive_empty_crawls}",
                    ];
                }
                if ($source->is_enabled
                    && $source->quality_status?->allowsAutomaticCrawl()
                    && $source->last_success_at
                    && $source->last_success_at->lt(now()->subDays($staleDays))
                ) {
                    $alerts[] = [
                        'type' => 'stale_success',
                        'severity' => 'medium',
                        'job_source_id' => $source->id,
                        'slug' => $source->slug,
                        'name' => $source->name,
                        'message' => 'بیش از '.$staleDays.' روز بدون خزش موفق',
                    ];
                }
                $found = (int) $source->lifetime_jobs_found;
                $rejected = (int) $source->lifetime_rejected;
                $minFound = (int) config('aggregation.health.high_rejection_min_found', 3);
                $rate = (float) config('aggregation.health.high_rejection_rate', 0.8);
                if ($found >= $minFound && ($rejected / max(1, $found)) >= $rate) {
                    $alerts[] = [
                        'type' => 'high_rejection',
                        'severity' => 'medium',
                        'job_source_id' => $source->id,
                        'slug' => $source->slug,
                        'name' => $source->name,
                        'message' => 'نرخ رد بالا در طول عمر منبع',
                    ];
                }
            });

        return $alerts;
    }

    protected function qualityAfterHttpFailure(JobSource $source, int $failures): JobSourceQualityStatus
    {
        $current = $source->quality_status ?? JobSourceQualityStatus::Active;
        if ($current === JobSourceQualityStatus::ManualOnly) {
            return JobSourceQualityStatus::ManualOnly;
        }

        $threshold = (int) config('aggregation.health.consecutive_failure_threshold', 3);
        if ($failures >= $threshold
            && in_array($current, [JobSourceQualityStatus::Active, JobSourceQualityStatus::Limited], true)
        ) {
            return JobSourceQualityStatus::TemporarilyUnavailable;
        }

        return $current;
    }

    protected function qualityAfterEmptySuccess(JobSource $source, bool $isManualTest): JobSourceQualityStatus
    {
        $current = $source->quality_status ?? JobSourceQualityStatus::Active;
        if ($current === JobSourceQualityStatus::ManualOnly) {
            return JobSourceQualityStatus::ManualOnly;
        }

        // Reachable with zero employment items → LIMITED (not a failure).
        if ($current === JobSourceQualityStatus::TemporarilyUnavailable) {
            return JobSourceQualityStatus::Limited;
        }

        if ($current === JobSourceQualityStatus::Active) {
            return JobSourceQualityStatus::Limited;
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function qualityAfterSuccessWithData(JobSource $source, array $summary, bool $isManualTest): JobSourceQualityStatus
    {
        $current = $source->quality_status ?? JobSourceQualityStatus::Active;
        if ($current === JobSourceQualityStatus::ManualOnly) {
            return JobSourceQualityStatus::ManualOnly;
        }

        $found = (int) ($summary['found'] ?? 0);
        $rejected = (int) ($summary['rejected'] ?? 0);
        $minFound = (int) config('aggregation.health.high_rejection_min_found', 3);
        $rate = (float) config('aggregation.health.high_rejection_rate', 0.8);
        $highRejection = $found >= $minFound && ($rejected / max(1, $found)) >= $rate;

        if ($current === JobSourceQualityStatus::TemporarilyUnavailable) {
            return $highRejection ? JobSourceQualityStatus::Limited : JobSourceQualityStatus::Active;
        }

        if ($highRejection) {
            return JobSourceQualityStatus::Limited;
        }

        if (in_array($current, [JobSourceQualityStatus::Limited, JobSourceQualityStatus::Active], true)) {
            return JobSourceQualityStatus::Active;
        }

        return $current;
    }

    protected function qualityAfterHighRejection(JobSource $source): JobSourceQualityStatus
    {
        $current = $source->quality_status ?? JobSourceQualityStatus::Active;
        if ($current === JobSourceQualityStatus::ManualOnly) {
            return JobSourceQualityStatus::ManualOnly;
        }

        return JobSourceQualityStatus::Limited;
    }

    protected function computeBackoffUntil(int $failures): Carbon
    {
        $steps = config('aggregation.health.backoff_minutes', [0, 30, 120, 360, 1440]);
        if (! is_array($steps) || $steps === []) {
            $steps = [0, 30, 120, 360, 1440];
        }
        $index = min($failures, count($steps) - 1);
        $minutes = max(0, (int) ($steps[$index] ?? 30));

        return now()->addMinutes($minutes);
    }

    protected function isHttpFailureStatus(?int $http): bool
    {
        if ($http === null) {
            return true;
        }
        if ($http === 403 || $http === 401 || $http === 429) {
            return true;
        }
        if ($http >= 500) {
            return true;
        }

        return false;
    }

    protected function normalizeHttpStatus(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 'error') {
            return null;
        }

        return (int) $value;
    }

    protected function appendQualityNote(?string $existing, string $line): string
    {
        $stamp = now()->toDateTimeString();
        $entry = "[{$stamp}] {$line}";
        $merged = trim((string) $existing);
        if ($merged === '') {
            return $entry;
        }

        $lines = preg_split("/\r\n|\n|\r/", $merged) ?: [];
        $lines[] = $entry;
        $lines = array_slice($lines, -20);

        return implode("\n", $lines);
    }
}
