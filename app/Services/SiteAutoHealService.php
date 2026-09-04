<?php

namespace App\Services;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Enums\Aggregation\JobSourceQualityStatus;
use App\Models\CrawlerError;
use App\Models\CrawlerRun;
use App\Models\JobSource;
use App\Models\SiteError;
use App\Services\Aggregation\AggregationScheduleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Periodic housekeeping that keeps admin dashboards fast and clears noise.
 */
class SiteAutoHealService
{
    /**
     * @return array<string, int>
     */
    public function run(bool $aggressive = false): array
    {
        $stats = [
            'crawler_runs_deleted' => 0,
            'crawler_errors_deleted' => 0,
            'site_errors_resolved' => 0,
            'site_errors_deleted' => 0,
            'failed_jobs_deleted' => 0,
            'aggregation_flag_healed' => 0,
            'aggregation_sources_healed' => 0,
            'aggregation_schedule_healed' => 0,
        ];

        try {
            $stats['crawler_runs_deleted'] = $this->pruneFailedCrawlerRuns($aggressive);
            $stats['crawler_errors_deleted'] = $this->pruneOrphanCrawlerErrors();
            $stats['site_errors_resolved'] = $this->autoResolveTransientSiteErrors();
            $stats['site_errors_deleted'] = $this->pruneOldResolvedSiteErrors();
            $stats['failed_jobs_deleted'] = $this->pruneFailedJobs($aggressive);
            $stats['aggregation_flag_healed'] = $this->healJobCrawlerFlag();
            $stats['aggregation_sources_healed'] = $this->healAggregationSourceBackoff();
            $stats['aggregation_schedule_healed'] = $this->healAggregationSchedule();
        } catch (Throwable $e) {
            Log::warning('SiteAutoHealService failed', ['error' => $e->getMessage()]);
        }

        return $stats;
    }

    /**
     * If whitelist sources exist but job-crawler was accidentally disabled, re-enable it.
     */
    public function healJobCrawlerFlag(): int
    {
        if (! Schema::hasTable('job_sources') || ! Schema::hasTable('features')) {
            return 0;
        }

        $hasSources = DB::table('job_sources')->exists();
        if (! $hasSources) {
            return 0;
        }

        $features = app(FeatureFlagService::class);
        if ($features->isEnabled('job-crawler', true)) {
            return 0;
        }

        $features->enable('job-crawler');

        return 1;
    }

    /**
     * Clear backoff / false demotions caused by transient SSRF false-positives (private CDN IPs)
     * or expired backoffs so whitelist sources can crawl again.
     */
    public function healAggregationSourceBackoff(): int
    {
        if (! Schema::hasTable('job_sources')) {
            return 0;
        }

        $permanentSlugs = collect(config('aggregation.official_sources', config('aggregation.pilot_sources', [])))
            ->filter(fn ($row) => is_array($row) && ($row['quality_status'] ?? '') === JobSourceQualityStatus::TemporarilyUnavailable->value)
            ->map(fn ($row) => (string) ($row['slug'] ?? ''))
            ->filter()
            ->values()
            ->all();

        $healed = 0;

        JobSource::query()
            ->where('is_enabled', true)
            ->where('is_approved', true)
            ->when($permanentSlugs !== [], fn ($q) => $q->whereNotIn('slug', $permanentSlugs))
            ->where(function ($q) {
                $q->whereNotNull('health_backoff_until')
                    ->orWhere('consecutive_failures', '>', 0)
                    ->orWhere('quality_status', JobSourceQualityStatus::TemporarilyUnavailable->value)
                    ->orWhere('last_crawl_outcome', 'http_failure');
            })
            ->orderBy('id')
            ->chunkById(50, function ($rows) use (&$healed) {
                foreach ($rows as $source) {
                    /** @var JobSource $source */
                    $notes = (string) ($source->quality_notes ?? '');
                    $blockedIp = str_contains($notes, 'blocked IP')
                        || str_contains($notes, 'Blocked IP')
                        || str_contains($notes, 'resolved to blocked');

                    $backoffExpired = $source->health_backoff_until === null
                        || $source->health_backoff_until->isPast();

                    $demoted = $source->quality_status === JobSourceQualityStatus::TemporarilyUnavailable;

                    // Only auto-promote demotions that look like false SSRF / expired backoff, not known WAF deaths.
                    if ($demoted && ! $blockedIp && ! $backoffExpired) {
                        continue;
                    }

                    $updates = [
                        'consecutive_failures' => 0,
                        'health_backoff_until' => null,
                    ];

                    if ($demoted) {
                        $updates['quality_status'] = JobSourceQualityStatus::Limited;
                        $updates['quality_notes'] = trim($notes."\n[auto-heal] بازنشانی پس از رفع بلاک IP خصوصی CDN / بک‌آف منقضی.");
                    }

                    $source->fill($updates);
                    if ($source->isDirty()) {
                        $source->save();
                        $healed++;
                    }
                }
            });

        return $healed;
    }

    /**
     * If whitelist sources exist but schedule was never enabled, turn on default daily slots.
     */
    public function healAggregationSchedule(): int
    {
        if (! Schema::hasTable('job_sources') || ! Schema::hasTable('settings')) {
            return 0;
        }

        if (! JobSource::query()->where('is_enabled', true)->where('is_approved', true)->exists()) {
            return 0;
        }

        $schedule = app(AggregationScheduleService::class);
        $config = $schedule->get();
        $hasEnabledTimes = collect($config['times'] ?? [])->contains(fn ($t) => ($t['enabled'] ?? false) && filled($t['time'] ?? null));

        if (($config['enabled'] ?? false) && $hasEnabledTimes) {
            return 0;
        }

        $times = $config['times'] ?? [];
        if (! $hasEnabledTimes) {
            foreach (['06:30', '12:30', '18:30'] as $time) {
                $times[] = [
                    'id' => (string) Str::uuid(),
                    'time' => $time,
                    'enabled' => true,
                    'label' => 'خزش خودکار',
                ];
            }
        }

        $schedule->update([
            'enabled' => true,
            'timezone' => $config['timezone'] ?: 'Asia/Tehran',
            'times' => $times,
        ]);

        return 1;
    }

    public function pruneFailedCrawlerRuns(bool $aggressive = false): int
    {
        $statuses = [
            CrawlerRunStatus::Failed->value,
            CrawlerRunStatus::Partial->value,
        ];

        $query = CrawlerRun::query()->whereIn('status', $statuses);

        if (! $aggressive) {
            $query->where(function ($q) {
                $q->where('finished_at', '<', now()->subHours(6))
                    ->orWhere(function ($w) {
                        $w->whereNull('finished_at')
                            ->where('created_at', '<', now()->subHours(6));
                    });
            });
        }

        $ids = $query->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        CrawlerError::query()->whereIn('crawler_run_id', $ids)->delete();

        return (int) CrawlerRun::query()->whereIn('id', $ids)->delete();
    }

    public function pruneOrphanCrawlerErrors(): int
    {
        return (int) CrawlerError::query()
            ->where(function ($q) {
                $q->whereNull('crawler_run_id')
                    ->orWhere('occurred_at', '<', now()->subDays(14));
            })
            ->delete();
    }

    public function autoResolveTransientSiteErrors(): int
    {
        $needles = [
            'Too Many Attempts',
            'Connection refused',
            'cURL error',
            'CSRF',
            'Unauthenticated',
            'token mismatch',
            'Operation timed out',
            'SSL',
            'proc_open',
            'Process class relies',
            'NamespaceNotFoundException',
            'There are no commands defined in the "user" namespace',
            'There are no commands defined in the "test" namespace',
            'missing: "queues"',
            'missing: queues',
            'missing: "mobile"',
            'missing: mobile',
        ];

        $count = 0;
        SiteError::query()
            ->whereNull('resolved_at')
            ->where('last_seen_at', '<', now()->subDay())
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($needles, &$count) {
                foreach ($rows as $row) {
                    $hay = ($row->message ?? '').' '.($row->exception_class ?? '');
                    foreach ($needles as $needle) {
                        if (stripos($hay, $needle) !== false) {
                            $row->update(['resolved_at' => now()]);
                            $count++;
                            break;
                        }
                    }
                }
            });

        return $count;
    }

    public function pruneOldResolvedSiteErrors(): int
    {
        return (int) SiteError::query()
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<', now()->subDays(7))
            ->delete();
    }

    public function pruneFailedJobs(bool $aggressive = false): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        $query = DB::table('failed_jobs');
        if (! $aggressive) {
            $query->where('failed_at', '<', now()->subDays(3));
        }

        return (int) $query->delete();
    }
}
