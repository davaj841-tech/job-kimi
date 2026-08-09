<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Aggregation\JobCrawlerType;
use App\Enums\Aggregation\JobEndpointType;
use App\Enums\Aggregation\JobSourceQualityStatus;
use App\Enums\Aggregation\JobSourceReliability;
use App\Enums\Aggregation\JobSourceType;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Admin\JobSourceEndpointStoreRequest;
use App\Http\Requests\Api\Admin\JobSourceStoreRequest;
use App\Models\JobSource;
use App\Models\JobSourceEndpoint;
use App\Services\Aggregation\CrawlOrchestrator;
use App\Services\Aggregation\JobSourceDomainGuard;
use App\Services\Aggregation\Parsers\SourceParserRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class JobSourceAdminController extends BaseController
{
    public function __construct(
        protected JobSourceDomainGuard $domains,
        protected CrawlOrchestrator $orchestrator,
        protected SourceParserRegistry $parsers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = JobSource::query()->withCount(['endpoints', 'jobPosts', 'crawlerRuns']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->string('source_type')->toString());
        }
        if ($request->filled('reliability_level')) {
            $query->where('reliability_level', $request->string('reliability_level')->toString());
        }
        if ($request->has('is_enabled') && $request->input('is_enabled') !== '') {
            $query->where('is_enabled', filter_var($request->input('is_enabled'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->has('is_approved') && $request->input('is_approved') !== '') {
            $query->where('is_approved', filter_var($request->input('is_approved'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('quality_status')) {
            $query->where('quality_status', $request->string('quality_status')->toString());
        }

        $sources = $query->orderBy('priority')->orderBy('id')
            ->paginate((int) $request->input('per_page', 20));

        return $this->successResponse([
            'data' => collect($sources->items())->map(fn (JobSource $s) => $this->serializeSource($s))->values(),
            'meta' => [
                'current_page' => $sources->currentPage(),
                'last_page' => $sources->lastPage(),
                'per_page' => $sources->perPage(),
                'total' => $sources->total(),
            ],
        ]);
    }

    public function options(): JsonResponse
    {
        return $this->successResponse([
            'source_types' => collect(JobSourceType::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values(),
            'reliability_levels' => collect(JobSourceReliability::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values(),
            'quality_statuses' => collect(JobSourceQualityStatus::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values(),
            'crawler_types' => collect(JobCrawlerType::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values(),
            'endpoint_types' => collect(JobEndpointType::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->value,
            ])->values(),
            'parser_types' => collect($this->parsers->keys())->map(fn ($k) => [
                'value' => $k,
                'label' => $k,
            ])->values(),
            'http_methods' => ['GET'],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $source = JobSource::query()->with(['endpoints'])->withCount(['jobPosts', 'crawlerRuns'])->find($id);
        if (! $source) {
            return $this->errorResponse('منبع یافت نشد.', 404);
        }

        return $this->successResponse($this->serializeSource($source, true));
    }

    public function store(JobSourceStoreRequest $request): JsonResponse
    {
        try {
            $payload = $this->prepareSourcePayload($request->validated());
            $this->domains->assertUrlBelongsToSource($payload['official_url'], new JobSource([
                'domain' => $payload['domain'],
                'official_url' => $payload['official_url'],
            ]));
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        $source = JobSource::query()->create($payload);

        return $this->successResponse($this->serializeSource($source->fresh()), 'منبع ایجاد شد.', 201);
    }

    public function update(JobSourceStoreRequest $request, int $id): JsonResponse
    {
        $source = JobSource::query()->find($id);
        if (! $source) {
            return $this->errorResponse('منبع یافت نشد.', 404);
        }

        try {
            $payload = $this->prepareSourcePayload($request->validated(), $source);
            $this->domains->assertUrlBelongsToSource($payload['official_url'], new JobSource([
                'domain' => $payload['domain'],
                'official_url' => $payload['official_url'],
            ]));
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        $source->update($payload);

        return $this->successResponse($this->serializeSource($source->fresh()->load('endpoints')), 'منبع به‌روزرسانی شد.');
    }

    public function destroy(int $id): JsonResponse
    {
        $source = JobSource::query()->find($id);
        if (! $source) {
            return $this->errorResponse('منبع یافت نشد.', 404);
        }

        if ($source->jobPosts()->exists()) {
            return $this->errorResponse('این منبع دارای آگهی تجمیع‌شده است و قابل حذف نیست. ابتدا آن را غیرفعال کنید.', 422);
        }

        $source->endpoints()->delete();
        $source->delete();

        return $this->successResponse(null, 'منبع حذف شد.');
    }

    public function approve(int $id): JsonResponse
    {
        return $this->toggleFlag($id, 'is_approved', true, 'منبع تایید شد.');
    }

    public function unapprove(int $id): JsonResponse
    {
        return $this->toggleFlag($id, 'is_approved', false, 'تایید منبع لغو شد.');
    }

    public function enable(int $id): JsonResponse
    {
        return $this->toggleFlag($id, 'is_enabled', true, 'منبع فعال شد.');
    }

    public function disable(int $id): JsonResponse
    {
        return $this->toggleFlag($id, 'is_enabled', false, 'منبع غیرفعال شد.');
    }

    /**
     * Manual crawl test for one whitelisted source. Jobs stay pending.
     */
    public function testCrawl(int $id): JsonResponse
    {
        $source = JobSource::query()->with('endpoints')->find($id);
        if (! $source) {
            return $this->errorResponse('منبع یافت نشد.', 404);
        }

        if (! $source->is_enabled || ! $source->is_approved) {
            return $this->errorResponse('فقط منابع فعال و تاییدشده قابل تست خزیدن هستند.', 422);
        }

        try {
            $result = $this->orchestrator->crawlSource($source->fresh(), true);
        } catch (Throwable $e) {
            return $this->errorResponse('خطا در اجرای تست: '.$e->getMessage(), 422);
        }

        $run = $result['run']->fresh(['errors']);
        $freshSource = $result['source'] ?? $source->fresh();

        return $this->successResponse([
            'summary' => $result['summary'],
            'health' => $result['health'] ?? null,
            'quality_status' => $freshSource->quality_status?->value,
            'quality_status_label' => $freshSource->quality_status?->label(),
            'source' => $this->serializeSource($freshSource),
            'run' => $this->serializeRun($run),
            'note' => 'آگهی‌های جدید در وضعیت pending ذخیره می‌شوند و منتشر نمی‌شوند.',
        ], 'تست خزیدن انجام شد.');
    }

    /**
     * Reset consecutive health counters / backoff. Never changes approval.
     */
    public function resetHealth(int $id): JsonResponse
    {
        $source = JobSource::query()->find($id);
        if (! $source) {
            return $this->errorResponse('منبع یافت نشد.', 404);
        }

        $updated = app(\App\Services\Aggregation\SourceHealthService::class)->resetCounters($source);

        return $this->successResponse($this->serializeSource($updated), 'شمارنده‌های سلامت بازنشانی شد.');
    }

    public function storeEndpoint(JobSourceEndpointStoreRequest $request, int $id): JsonResponse
    {
        $source = JobSource::query()->find($id);
        if (! $source) {
            return $this->errorResponse('منبع یافت نشد.', 404);
        }

        $data = $request->validated();
        try {
            $this->domains->assertUrlBelongsToSource($data['url'], $source);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        $endpoint = $source->endpoints()->create([
            'url' => $data['url'],
            'endpoint_type' => $data['endpoint_type'],
            'http_method' => strtoupper($data['http_method'] ?? 'GET'),
            'parser_type' => $data['parser_type'] ?? null,
            'is_enabled' => $data['is_enabled'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return $this->successResponse($this->serializeEndpoint($endpoint), 'Endpoint ایجاد شد.', 201);
    }

    public function updateEndpoint(JobSourceEndpointStoreRequest $request, int $id, int $endpointId): JsonResponse
    {
        $source = JobSource::query()->find($id);
        if (! $source) {
            return $this->errorResponse('منبع یافت نشد.', 404);
        }

        $endpoint = JobSourceEndpoint::query()->where('job_source_id', $source->id)->find($endpointId);
        if (! $endpoint) {
            return $this->errorResponse('Endpoint یافت نشد.', 404);
        }

        $data = $request->validated();
        try {
            $this->domains->assertUrlBelongsToSource($data['url'], $source);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        $endpoint->update([
            'url' => $data['url'],
            'endpoint_type' => $data['endpoint_type'],
            'http_method' => strtoupper($data['http_method'] ?? 'GET'),
            'parser_type' => $data['parser_type'] ?? null,
            'is_enabled' => array_key_exists('is_enabled', $data) ? $data['is_enabled'] : $endpoint->is_enabled,
            'sort_order' => $data['sort_order'] ?? $endpoint->sort_order,
        ]);

        return $this->successResponse($this->serializeEndpoint($endpoint->fresh()), 'Endpoint به‌روزرسانی شد.');
    }

    public function destroyEndpoint(int $id, int $endpointId): JsonResponse
    {
        $endpoint = JobSourceEndpoint::query()->where('job_source_id', $id)->find($endpointId);
        if (! $endpoint) {
            return $this->errorResponse('Endpoint یافت نشد.', 404);
        }
        $endpoint->delete();

        return $this->successResponse(null, 'Endpoint حذف شد.');
    }

    protected function toggleFlag(int $id, string $field, bool $value, string $message): JsonResponse
    {
        $source = JobSource::query()->find($id);
        if (! $source) {
            return $this->errorResponse('منبع یافت نشد.', 404);
        }
        $source->update([$field => $value]);

        return $this->successResponse($this->serializeSource($source->fresh()), $message);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareSourcePayload(array $data, ?JobSource $existing = null): array
    {
        $domain = $this->domains->normalizeDomain($data['domain'] ?? null)
            ?: $this->domains->normalizeDomain($data['official_url']);

        if ($domain === null) {
            throw new InvalidArgumentException('دامنه منبع قابل استخراج نیست.');
        }

        $this->domains->assertUrlBelongsToSource($data['official_url'], new JobSource([
            'domain' => $domain,
            'official_url' => $data['official_url'],
        ]));

        $slug = $data['slug'] ?? null;
        if (! filled($slug)) {
            $slug = $existing?->slug ?: (Str::slug($data['name']) ?: 'source-'.Str::random(6));
        }

        return [
            'name' => $data['name'],
            'slug' => $slug,
            'official_url' => $data['official_url'],
            'domain' => $domain,
            'source_type' => $data['source_type'],
            'reliability_level' => $data['reliability_level'],
            'priority' => (int) ($data['priority'] ?? $existing?->priority ?? 50),
            'is_enabled' => array_key_exists('is_enabled', $data)
                ? (bool) $data['is_enabled']
                : ($existing?->is_enabled ?? false),
            'is_approved' => array_key_exists('is_approved', $data)
                ? (bool) $data['is_approved']
                : ($existing?->is_approved ?? false),
            'quality_status' => $data['quality_status']
                ?? $existing?->quality_status?->value
                ?? JobSourceQualityStatus::Active->value,
            'crawler_type' => $data['crawler_type'],
            'crawl_frequency' => $data['crawl_frequency'] ?? $existing?->crawl_frequency ?? 'daily',
            'schedule_mode' => $data['schedule_mode'] ?? $existing?->schedule_mode ?? 'global',
            'custom_schedule_times' => $this->normalizeCustomTimes(
                $data['custom_schedule_times'] ?? $existing?->custom_schedule_times
            ),
            'notes' => $data['notes'] ?? $existing?->notes,
            'quality_notes' => array_key_exists('quality_notes', $data)
                ? $data['quality_notes']
                : ($existing?->quality_notes),
        ];
    }

    /**
     * @param  mixed  $times
     * @return list<array{time: string, enabled: bool, label: ?string}>|null
     */
    protected function normalizeCustomTimes(mixed $times): ?array
    {
        if (! is_array($times)) {
            return null;
        }

        $out = [];
        $seen = [];
        foreach ($times as $row) {
            if (! is_array($row) || empty($row['time'])) {
                continue;
            }
            if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', trim((string) $row['time']), $m)) {
                continue;
            }
            $time = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
            if (isset($seen[$time])) {
                continue;
            }
            $seen[$time] = true;
            $out[] = [
                'time' => $time,
                'enabled' => (bool) ($row['enabled'] ?? true),
                'label' => filled($row['label'] ?? null) ? (string) $row['label'] : null,
            ];
        }

        return $out;
    }

    protected function serializeSource(JobSource $source, bool $withEndpoints = false): array
    {
        $row = [
            'id' => $source->id,
            'name' => $source->name,
            'slug' => $source->slug,
            'official_url' => $source->official_url,
            'domain' => $source->domain,
            'source_type' => $source->source_type?->value,
            'source_type_label' => $source->source_type?->label(),
            'reliability_level' => $source->reliability_level?->value,
            'reliability_label' => $source->reliability_level?->label(),
            'priority' => $source->priority,
            'is_enabled' => (bool) $source->is_enabled,
            'is_approved' => (bool) $source->is_approved,
            'is_whitelisted' => (bool) ($source->is_enabled && $source->is_approved),
            'quality_status' => $source->quality_status?->value ?? JobSourceQualityStatus::Active->value,
            'quality_status_label' => $source->quality_status?->label()
                ?? JobSourceQualityStatus::Active->label(),
            'allows_automatic_crawl' => $source->allowsAutomaticCrawl(),
            'crawler_type' => $source->crawler_type?->value,
            'crawler_type_label' => $source->crawler_type?->label(),
            'crawl_frequency' => $source->crawl_frequency,
            'schedule_mode' => $source->schedule_mode ?: 'global',
            'custom_schedule_times' => $source->custom_schedule_times,
            'last_crawled_at' => $source->last_crawled_at?->toIso8601String(),
            'last_success_at' => $source->last_success_at?->toIso8601String(),
            'last_failure_at' => $source->last_failure_at?->toIso8601String(),
            'notes' => $source->notes,
            'quality_notes' => $source->quality_notes,
            'consecutive_failures' => (int) $source->consecutive_failures,
            'consecutive_empty_crawls' => (int) $source->consecutive_empty_crawls,
            'total_successful_crawls' => (int) $source->total_successful_crawls,
            'total_failed_crawls' => (int) $source->total_failed_crawls,
            'total_empty_successful_crawls' => (int) $source->total_empty_successful_crawls,
            'lifetime_jobs_found' => (int) $source->lifetime_jobs_found,
            'lifetime_jobs_created' => (int) $source->lifetime_jobs_created,
            'lifetime_jobs_updated' => (int) $source->lifetime_jobs_updated,
            'lifetime_duplicates' => (int) $source->lifetime_duplicates,
            'lifetime_rejected' => (int) $source->lifetime_rejected,
            'lifetime_validation_errors' => (int) $source->lifetime_validation_errors,
            'last_http_status' => $source->last_http_status,
            'last_crawl_outcome' => $source->last_crawl_outcome,
            'health_backoff_until' => $source->health_backoff_until?->toIso8601String(),
            'in_backoff' => $source->health_backoff_until?->isFuture() ?? false,
            'endpoints_count' => $source->endpoints_count ?? $source->endpoints()->count(),
            'job_posts_count' => $source->job_posts_count ?? null,
            'crawler_runs_count' => $source->crawler_runs_count ?? null,
            'allows_auto_publish_eligible' => $source->allowsAutoPublish(),
            'created_at' => $source->created_at?->toIso8601String(),
            'updated_at' => $source->updated_at?->toIso8601String(),
        ];

        if ($withEndpoints || $source->relationLoaded('endpoints')) {
            $row['endpoints'] = $source->endpoints->map(fn ($e) => $this->serializeEndpoint($e))->values();
        }

        return $row;
    }

    protected function serializeEndpoint(JobSourceEndpoint $endpoint): array
    {
        return [
            'id' => $endpoint->id,
            'job_source_id' => $endpoint->job_source_id,
            'url' => $endpoint->url,
            'endpoint_type' => $endpoint->endpoint_type?->value,
            'http_method' => $endpoint->http_method,
            'parser_type' => $endpoint->parser_type,
            'is_enabled' => (bool) $endpoint->is_enabled,
            'sort_order' => $endpoint->sort_order,
        ];
    }

    protected function serializeRun($run): array
    {
        return [
            'id' => $run->id,
            'job_source_id' => $run->job_source_id,
            'status' => $run->status?->value,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'execution_ms' => $run->execution_ms,
            'jobs_found' => $run->jobs_found,
            'jobs_created' => $run->jobs_created,
            'jobs_updated' => $run->jobs_updated,
            'duplicates' => $run->duplicates,
            'errors_count' => $run->errors_count,
            'meta' => is_array($run->meta) ? $run->meta : null,
            'errors' => $run->relationLoaded('errors')
                ? $run->errors->map(fn ($e) => [
                    'id' => $e->id,
                    'error_type' => $e->error_type,
                    'message' => $e->message,
                    'url' => $e->url,
                    'occurred_at' => $e->occurred_at?->toIso8601String(),
                    'context' => $this->domains->sanitizeContext($e->context),
                ])->values()
                : [],
        ];
    }
}
