<?php

namespace App\Services\Aggregation;

use App\Models\CrawlerError;
use App\Models\CrawlerRun;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Services\FeatureFlagService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

/**
 * Production health snapshot + structured admin alerts (no schema changes).
 */
class AggregationHealthService
{
    public const ALERT_STALE_CRAWL = 'stale_crawl';

    public const ALERT_ALL_SOURCES_FAILED = 'all_sources_failed';

    public const ALERT_FEATURE_DISABLED = 'feature_flag_disabled';

    public const ALERT_PENDING_BACKLOG = 'pending_jobs_backlog';

    public function __construct(
        protected FeatureFlagService $features,
        protected AggregationScheduleService $schedule,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $featureEnabled = $this->features->isEnabled('job-crawler', true);
        $scheduleConfig = $this->schedule->get();
        $scheduleEnabled = (bool) ($scheduleConfig['enabled'] ?? false);
        $enabledTimes = $this->schedule->enabledGlobalTimes();
        $staleHours = $this->staleCrawlHours();
        $pendingThreshold = $this->pendingJobsThreshold();

        $sourcesEnabled = 0;
        $sourcesDispatchable = 0;
        $sourcesTotal = 0;
        $sourcesFailed = 0;
        $sourcesCrawled = 0;
        $lastCrawledAt = null;
        $pendingJobs = 0;
        $approvedJobs = 0;
        $lastError = null;
        $lastRun = null;

        if (Schema::hasTable('job_sources')) {
            $sourcesTotal = JobSource::query()->count();
            $sourcesEnabled = JobSource::query()->where('is_enabled', true)->count();
            $sourcesDispatchable = JobSource::query()->dispatchable()->count();
            $lastCrawled = JobSource::query()
                ->whereNotNull('last_crawled_at')
                ->orderByDesc('last_crawled_at')
                ->value('last_crawled_at');
            $lastCrawledAt = $lastCrawled instanceof CarbonInterface
                ? $lastCrawled->toIso8601String()
                : (is_string($lastCrawled) ? $lastCrawled : null);

            $whitelisted = JobSource::query()->whitelisted()->get(['id', 'last_crawled_at', 'last_crawl_outcome', 'consecutive_failures']);
            $sourcesCrawled = $whitelisted->filter(fn (JobSource $s) => $s->last_crawled_at !== null)->count();
            $sourcesFailed = $whitelisted
                ->filter(fn (JobSource $s) => $s->last_crawled_at !== null && $this->isFailureOutcome($s->last_crawl_outcome))
                ->count();
        }

        if (Schema::hasTable('job_posts')) {
            $pendingJobs = JobPost::query()
                ->whereNotNull('job_source_id')
                ->where('status', 'pending')
                ->count();
            $approvedJobs = JobPost::query()
                ->whereNotNull('job_source_id')
                ->where('status', 'approved')
                ->count();
        }

        if (Schema::hasTable('crawler_runs')) {
            $run = CrawlerRun::query()
                ->with('source:id,name,domain')
                ->orderByDesc('id')
                ->first();
            if ($run) {
                $lastRun = [
                    'id' => $run->id,
                    'status' => $run->status instanceof \BackedEnum
                        ? $run->status->value
                        : (string) $run->status,
                    'source_name' => $run->source?->name,
                    'started_at' => $run->started_at?->toIso8601String(),
                    'finished_at' => $run->finished_at?->toIso8601String(),
                    'jobs_created' => (int) ($run->jobs_created ?? 0),
                    'errors_count' => (int) ($run->errors_count ?? 0),
                ];
            }
        }

        if (Schema::hasTable('crawler_errors')) {
            $error = CrawlerError::query()
                ->with('source:id,name')
                ->orderByDesc('id')
                ->first();
            if ($error) {
                $lastError = [
                    'id' => $error->id,
                    'error_type' => $error->error_type,
                    'message' => $this->truncate((string) ($error->message ?? ''), 240),
                    'url' => $error->url,
                    'source_name' => $error->source?->name,
                    'occurred_at' => $error->occurred_at?->toIso8601String()
                        ?? $error->created_at?->toIso8601String(),
                ];
            }
        }

        $context = [
            'feature_enabled' => $featureEnabled,
            'schedule_enabled' => $scheduleEnabled,
            'enabled_times' => $enabledTimes,
            'sources_enabled' => $sourcesEnabled,
            'sources_dispatchable' => $sourcesDispatchable,
            'sources_whitelisted_crawled' => $sourcesCrawled,
            'sources_whitelisted_failed' => $sourcesFailed,
            'last_crawled_at' => $lastCrawledAt,
            'last_error' => $lastError,
            'pending_jobs' => $pendingJobs,
            'stale_hours' => $staleHours,
            'pending_threshold' => $pendingThreshold,
        ];

        $alerts = $this->buildAlerts($context);
        $checks = [
            'feature_flag' => $featureEnabled ? 'ok' : 'critical',
            'active_sources' => $sourcesDispatchable > 0 ? 'ok' : ($sourcesEnabled > 0 ? 'warn' : 'critical'),
            'scheduler' => $scheduleEnabled && $enabledTimes !== []
                ? 'ok'
                : 'warn',
            'last_crawl' => $this->lastCrawlCheck(
                $lastCrawledAt,
                $scheduleEnabled,
                $staleHours,
                $sourcesDispatchable
            ),
            'last_error' => $this->lastErrorCheck($lastError),
            'all_sources_failed' => $this->allSourcesFailed($context) ? 'critical' : 'ok',
            'pending_backlog' => $pendingJobs >= $pendingThreshold ? 'warn' : 'ok',
        ];

        return [
            'status' => $this->rollupStatus(array_merge($checks, array_column($alerts, 'severity', 'code'))),
            'checked_at' => now()->toIso8601String(),
            'feature_flag' => [
                'name' => 'job-crawler',
                'enabled' => $featureEnabled,
            ],
            'scheduler' => [
                'enabled' => $scheduleEnabled,
                'timezone' => (string) ($scheduleConfig['timezone'] ?? 'Asia/Tehran'),
                'times' => $enabledTimes,
                'current_slot' => $this->schedule->nowSlot(),
                'is_due_now' => $this->schedule->isDueNow(),
                'max_concurrent' => (int) ($scheduleConfig['max_concurrent'] ?? 5),
                'queue' => (string) config('aggregation.queue', 'crawlers'),
                'queue_connection' => (string) config('queue.default', 'database'),
            ],
            'sources' => [
                'total' => $sourcesTotal,
                'enabled' => $sourcesEnabled,
                'dispatchable' => $sourcesDispatchable,
                'crawled' => $sourcesCrawled,
                'failed' => $sourcesFailed,
            ],
            'jobs' => [
                'pending' => $pendingJobs,
                'approved' => $approvedJobs,
                'pending_threshold' => $pendingThreshold,
            ],
            'last_crawled_at' => $lastCrawledAt,
            'last_run' => $lastRun,
            'last_error' => $lastError,
            'checks' => $checks,
            'alerts' => $alerts,
            'issues' => array_values(array_map(fn (array $a) => $a['message'], $alerts)),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{code: string, severity: string, title: string, message: string, link: string}>
     */
    public function buildAlerts(array $context): array
    {
        $alerts = [];

        if (! ($context['feature_enabled'] ?? true)) {
            $alerts[] = [
                'code' => self::ALERT_FEATURE_DISABLED,
                'severity' => 'critical',
                'title' => 'خزشگر آگهی خاموش است',
                'message' => 'فلگ job-crawler خاموش است؛ دیسپاچ خزش اجرا نمی‌شود.',
                'link' => '/admin/aggregation-settings',
            ];
        }

        if ($this->isStaleCrawl(
            $context['last_crawled_at'] ?? null,
            (int) ($context['stale_hours'] ?? 6),
            (bool) ($context['schedule_enabled'] ?? false),
            (int) ($context['sources_dispatchable'] ?? 0),
        )) {
            $hours = (int) ($context['stale_hours'] ?? 6);
            $alerts[] = [
                'code' => self::ALERT_STALE_CRAWL,
                'severity' => 'warn',
                'title' => 'خزش عقب‌افتاده',
                'message' => $context['last_crawled_at']
                    ? "آخرین خزش بیش از {$hours} ساعت پیش بوده است."
                    : 'هنوز هیچ خزشی ثبت نشده است.',
                'link' => '/admin/crawl-monitoring',
            ];
        }

        if ($this->allSourcesFailed($context)) {
            $failed = (int) ($context['sources_whitelisted_failed'] ?? 0);
            $alerts[] = [
                'code' => self::ALERT_ALL_SOURCES_FAILED,
                'severity' => 'critical',
                'title' => 'همه منابع خزش ناموفق‌اند',
                'message' => "همه منابع whitelist که خزش شده‌اند ناموفق‌اند ({$failed} منبع).",
                'link' => '/admin/job-sources',
            ];
        }

        $pending = (int) ($context['pending_jobs'] ?? 0);
        $threshold = (int) ($context['pending_threshold'] ?? 50);
        if ($pending >= $threshold) {
            $alerts[] = [
                'code' => self::ALERT_PENDING_BACKLOG,
                'severity' => $pending >= ($threshold * 2) ? 'critical' : 'warn',
                'title' => 'صف بررسی آگهی‌ها شلوغ است',
                'message' => "{$pending} آگهی تجمیع‌شده در انتظار بررسی است (حد {$threshold}).",
                'link' => '/admin/aggregated-jobs',
            ];
        }

        return $alerts;
    }

    public function staleCrawlHours(): int
    {
        return max(1, (int) config('aggregation.alerts.stale_crawl_hours', 6));
    }

    public function pendingJobsThreshold(): int
    {
        return max(1, (int) config('aggregation.alerts.pending_jobs_threshold', 50));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function allSourcesFailed(array $context): bool
    {
        $crawled = (int) ($context['sources_whitelisted_crawled'] ?? 0);
        $failed = (int) ($context['sources_whitelisted_failed'] ?? 0);

        return $crawled > 0 && $failed === $crawled;
    }

    protected function isFailureOutcome(mixed $outcome): bool
    {
        return in_array((string) $outcome, [
            SourceHealthService::OUTCOME_HTTP_FAILURE,
            SourceHealthService::OUTCOME_QUALITY_FAILURE,
        ], true);
    }

    protected function isStaleCrawl(mixed $lastCrawledAt, int $hours, bool $scheduleEnabled = false, int $dispatchable = 0): bool
    {
        if (! is_string($lastCrawledAt) || $lastCrawledAt === '') {
            return $scheduleEnabled || $dispatchable > 0;
        }

        return Carbon::parse($lastCrawledAt)->lt(now()->subHours($hours));
    }

    protected function lastCrawlCheck(?string $lastCrawledAt, bool $scheduleEnabled, int $staleHours, int $dispatchable): string
    {
        if ($this->isStaleCrawl($lastCrawledAt, $staleHours, $scheduleEnabled, $dispatchable)) {
            return 'warn';
        }

        return 'ok';
    }

    /**
     * @param  array<string, mixed>|null  $lastError
     */
    protected function lastErrorCheck(?array $lastError): string
    {
        if ($lastError === null || empty($lastError['occurred_at'])) {
            return 'ok';
        }

        $at = Carbon::parse((string) $lastError['occurred_at']);
        if ($at->gte(now()->subHours(6))) {
            return 'warn';
        }

        return 'ok';
    }

    /**
     * @param  array<string, string>  $checks
     */
    protected function rollupStatus(array $checks): string
    {
        if (in_array('critical', $checks, true)) {
            return 'critical';
        }
        if (in_array('warn', $checks, true)) {
            return 'warn';
        }

        return 'ok';
    }

    protected function truncate(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1).'…';
    }
}
