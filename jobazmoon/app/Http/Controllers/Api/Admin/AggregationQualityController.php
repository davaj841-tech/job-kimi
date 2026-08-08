<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Enums\Aggregation\JobSourceQualityStatus;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Admin\AggregatedJobUpdateRequest;
use App\Http\Resources\JobPostResource;
use App\Models\CrawlerError;
use App\Models\CrawlerRun;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Repositories\JobPostRepository;
use App\Services\Aggregation\SourceHealthService;
use App\Services\JobPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AggregationQualityController extends BaseController
{
    public function __construct(
        protected JobPostRepository $jobPosts,
        protected JobPostService $jobPostService,
        protected SourceHealthService $health,
    ) {}

    public function stats(): JsonResponse
    {
        $aggregated = JobPost::query()->whereNotNull('job_source_id');

        $bySource = JobPost::query()
            ->whereNotNull('job_source_id')
            ->select('job_source_id', DB::raw('count(*) as total'))
            ->groupBy('job_source_id')
            ->get()
            ->map(function ($row) {
                $source = JobSource::query()->find($row->job_source_id);

                return [
                    'job_source_id' => (int) $row->job_source_id,
                    'source_name' => $source?->name,
                    'reliability_level' => $source?->reliability_level?->value,
                    'quality_status' => $source?->quality_status?->value,
                    'consecutive_failures' => (int) ($source?->consecutive_failures ?? 0),
                    'last_success_at' => $source?->last_success_at?->toIso8601String(),
                    'total' => (int) $row->total,
                ];
            })
            ->values();

        $byReliability = JobSource::query()
            ->select('reliability_level', DB::raw('count(job_posts.id) as total'))
            ->leftJoin('job_posts', 'job_posts.job_source_id', '=', 'job_sources.id')
            ->groupBy('reliability_level')
            ->get()
            ->map(fn ($row) => [
                'reliability_level' => $row->reliability_level instanceof \BackedEnum
                    ? $row->reliability_level->value
                    : $row->reliability_level,
                'total' => (int) $row->total,
            ])
            ->values();

        $recentFailures = CrawlerRun::query()
            ->with('source:id,name,domain,quality_status,consecutive_failures')
            ->whereIn('status', ['failed', 'partial'])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (CrawlerRun $run) => [
                'id' => $run->id,
                'source_name' => $run->source?->name,
                'status' => $run->status?->value,
                'errors_count' => $run->errors_count,
                'finished_at' => $run->finished_at?->toIso8601String(),
            ])
            ->values();

        $duplicateUpdates = (int) CrawlerRun::query()->sum('duplicates');
        $totalRuns = (int) CrawlerRun::query()->count();
        $successfulRuns = (int) CrawlerRun::query()->whereIn('status', [
            CrawlerRunStatus::Completed->value,
            CrawlerRunStatus::Partial->value,
        ])->count();
        $failedRuns = (int) CrawlerRun::query()->where('status', CrawlerRunStatus::Failed->value)->count();
        $emptySuccessful = (int) CrawlerRun::query()
            ->where('status', CrawlerRunStatus::Completed->value)
            ->where('jobs_found', 0)
            ->count();

        $qualityCounts = [
            'active' => JobSource::query()->ofQualityStatus(JobSourceQualityStatus::Active)->count(),
            'limited' => JobSource::query()->ofQualityStatus(JobSourceQualityStatus::Limited)->count(),
            'temporarily_unavailable' => JobSource::query()->ofQualityStatus(JobSourceQualityStatus::TemporarilyUnavailable)->count(),
            'manual_only' => JobSource::query()->ofQualityStatus(JobSourceQualityStatus::ManualOnly)->count(),
        ];

        $unhealthy = JobSource::query()
            ->where('is_approved', true)
            ->where(function ($q) {
                $q->where('consecutive_failures', '>=', (int) config('aggregation.health.consecutive_failure_threshold', 3))
                    ->orWhere('consecutive_empty_crawls', '>=', (int) config('aggregation.health.consecutive_empty_warning', 5))
                    ->orWhere('quality_status', JobSourceQualityStatus::TemporarilyUnavailable->value);
            })
            ->orderByDesc('consecutive_failures')
            ->limit(20)
            ->get(['id', 'name', 'slug', 'quality_status', 'consecutive_failures', 'consecutive_empty_crawls', 'last_success_at', 'last_failure_at', 'last_http_status', 'health_backoff_until'])
            ->map(fn (JobSource $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'quality_status' => $s->quality_status?->value,
                'consecutive_failures' => (int) $s->consecutive_failures,
                'consecutive_empty_crawls' => (int) $s->consecutive_empty_crawls,
                'last_success_at' => $s->last_success_at?->toIso8601String(),
                'last_failure_at' => $s->last_failure_at?->toIso8601String(),
                'last_http_status' => $s->last_http_status,
                'health_backoff_until' => $s->health_backoff_until?->toIso8601String(),
            ])
            ->values();

        return $this->successResponse([
            'total_aggregated_jobs' => (clone $aggregated)->count(),
            'pending_jobs' => (clone $aggregated)->where('status', 'pending')->count(),
            'published_jobs' => (clone $aggregated)->where('status', 'approved')->count(),
            'rejected_jobs' => (clone $aggregated)->where('status', 'rejected')->count(),
            'duplicate_updates' => $duplicateUpdates,
            'whitelisted_sources' => JobSource::query()->whitelisted()->count(),
            'total_sources' => JobSource::query()->count(),
            'sources_by_quality' => JobSource::query()
                ->select('quality_status', DB::raw('count(*) as total'))
                ->groupBy('quality_status')
                ->get()
                ->map(fn ($row) => [
                    'quality_status' => $row->quality_status instanceof \BackedEnum
                        ? $row->quality_status->value
                        : $row->quality_status,
                    'total' => (int) $row->total,
                ])
                ->values(),
            'source_health' => [
                'healthy' => $qualityCounts['active'],
                'limited' => $qualityCounts['limited'],
                'temporarily_unavailable' => $qualityCounts['temporarily_unavailable'],
                'manual_only' => $qualityCounts['manual_only'],
                'in_backoff' => JobSource::query()->whereNotNull('health_backoff_until')->where('health_backoff_until', '>', now())->count(),
                'unhealthy_sources' => $unhealthy,
            ],
            'crawl_quality' => [
                'total_crawls' => $totalRuns,
                'successful_crawls' => $successfulRuns,
                'failed_crawls' => $failedRuns,
                'empty_successful_crawls' => $emptySuccessful,
                'jobs_discovered' => (int) JobSource::query()->sum('lifetime_jobs_found'),
                'jobs_created' => (int) JobSource::query()->sum('lifetime_jobs_created'),
                'jobs_updated' => (int) JobSource::query()->sum('lifetime_jobs_updated'),
                'duplicates' => (int) JobSource::query()->sum('lifetime_duplicates'),
                'rejected_records' => (int) JobSource::query()->sum('lifetime_rejected'),
                'validation_errors' => (int) JobSource::query()->sum('lifetime_validation_errors'),
            ],
            'alerts' => $this->health->buildAlerts(),
            'auto_crawl_sources' => JobSource::query()->dispatchable()->count(),
            'recent_crawl_failures' => $recentFailures,
            'jobs_by_source' => $bySource,
            'jobs_by_reliability' => $byReliability,
            'recent_errors_count' => CrawlerError::query()->where('occurred_at', '>=', now()->subDays(7))->count(),
        ]);
    }

    public function pendingJobs(Request $request): JsonResponse
    {
        $filters = $request->only([
            'status', 'search', 'per_page', 'province', 'city', 'job_classification_id', 'deadline_from', 'deadline_to',
            'job_source_id',
        ]);
        $filters['aggregated_only'] = true;
        if (empty($filters['status'])) {
            $filters['status'] = 'pending';
        }

        $posts = $this->jobPosts->getAdminList($filters);

        return $this->successResponse([
            'data' => JobPostResource::collection($posts)->resolve(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function showJob(int $id): JsonResponse
    {
        $job = JobPost::query()
            ->with(['source', 'creator:id,name', 'approver:id,name', 'classification', 'attachments'])
            ->whereNotNull('job_source_id')
            ->find($id);

        if (! $job) {
            return $this->errorResponse('آگهی تجمیع‌شده یافت نشد.', 404);
        }

        return $this->successResponse(new JobPostResource($job));
    }

    public function updateJob(AggregatedJobUpdateRequest $request, int $id): JsonResponse
    {
        $job = JobPost::query()->whereNotNull('job_source_id')->find($id);
        if (! $job) {
            return $this->errorResponse('آگهی تجمیع‌شده یافت نشد.', 404);
        }

        $data = $request->validated();
        // Keep aggregation provenance intact.
        unset($data['job_source_id'], $data['external_id'], $data['content_hash']);
        if (! empty($data['provinces']) && empty($data['province'])) {
            $data['province'] = $data['provinces'][0] ?? $job->province;
        }

        $updated = $this->jobPostService->update($job, $data, []);

        return $this->successResponse(
            new JobPostResource($updated->load(['source', 'classification', 'attachments'])),
            'آگهی تجمیع‌شده ویرایش شد.'
        );
    }

    public function approveJob(int $id): JsonResponse
    {
        $job = JobPost::query()->whereNotNull('job_source_id')->find($id);
        if (! $job) {
            return $this->errorResponse('آگهی تجمیع‌شده یافت نشد.', 404);
        }

        try {
            $approved = $this->jobPostService->approve($id, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse(new JobPostResource($approved->load('source')), 'آگهی تجمیع‌شده منتشر شد.');
    }

    public function rejectJob(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        $job = JobPost::query()->whereNotNull('job_source_id')->find($id);
        if (! $job) {
            return $this->errorResponse('آگهی تجمیع‌شده یافت نشد.', 404);
        }

        try {
            $rejected = $this->jobPostService->reject($id, $request->input('reason'));
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse(new JobPostResource($rejected->load('source')), 'آگهی تجمیع‌شده رد شد.');
    }
}
