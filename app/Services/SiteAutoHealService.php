<?php

namespace App\Services;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Models\CrawlerError;
use App\Models\CrawlerRun;
use App\Models\SiteError;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        ];

        try {
            $stats['crawler_runs_deleted'] = $this->pruneFailedCrawlerRuns($aggressive);
            $stats['crawler_errors_deleted'] = $this->pruneOrphanCrawlerErrors();
            $stats['site_errors_resolved'] = $this->autoResolveTransientSiteErrors();
            $stats['site_errors_deleted'] = $this->pruneOldResolvedSiteErrors();
            $stats['failed_jobs_deleted'] = $this->pruneFailedJobs($aggressive);
        } catch (Throwable $e) {
            Log::warning('SiteAutoHealService failed', ['error' => $e->getMessage()]);
        }

        return $stats;
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
