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
use App\Models\CrawlerError;
use App\Models\CrawlerRun;
use App\Models\JobSource;
use App\Models\JobSourceEndpoint;
use App\Services\Aggregation\CrawlOrchestrator;
use App\Services\Aggregation\JobSourceDomainGuard;
use App\Services\Aggregation\Parsers\SourceParserRegistry;
use App\Services\Aggregation\SourceHealthService;
use Carbon\CarbonInterface;
use Database\Seeders\PilotJobSourceSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

    public function seedDefaults(): JsonResponse
    {
        $before = JobSource::query()->count();
        Artisan::call('db:seed', [
            '--class' => PilotJobSourceSeeder::class,
            '--force' => true,
        ]);

        // Re-activate seeded official sources so crawl works after prior failures/backoff.
        $reactivated = $this->reactivateOfficialSources();

        return $this->successResponse([
            'before' => $before,
            'after' => JobSource::query()->count(),
            'dispatchable' => JobSource::query()->dispatchable()->count(),
            'reactivated' => $reactivated,
        ], 'منابع پیش‌فرض بارگذاری و برای جستجو فعال شدند.');
    }

    /**
     * Enable + approve official seeded sources, clear health backoff, restore Active quality.
     */
    public function reactivateDefaults(): JsonResponse
    {
        $count = $this->reactivateOfficialSources();

        return $this->successResponse([
            'reactivated' => $count,
            'dispatchable' => JobSource::query()->dispatchable()->count(),
        ], 'منابع رسمی برای جستجوی خودکار فعال شدند.');
    }

    protected function reactivateOfficialSources(): int
    {
        $slugs = collect(config('aggregation.official_sources', []))
            ->merge(config('aggregation.pilot_sources', []))
            ->pluck('slug')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $query = JobSource::query();
        if ($slugs !== []) {
            $query->whereIn('slug', $slugs);
        }

        $sources = $query->with('endpoints')->get();
        $count = 0;

        foreach ($sources as $source) {
            $source->fill([
                'is_enabled' => true,
                'is_approved' => true,
                'quality_status' => JobSourceQualityStatus::Active,
                'consecutive_failures' => 0,
                'consecutive_empty_crawls' => 0,
                'health_backoff_until' => null,
            ]);
            $source->save();

            $source->endpoints()->update(['is_enabled' => true]);
            $count++;
        }

        // Ensure feature flag is on so scheduled dispatch can run.
        try {
            app(\App\Services\FeatureFlagService::class)->enable('job-crawler');
        } catch (\Throwable) {
            // optional
        }

        return $count;
    }

    /**
     * Active crawl targets + catalog summary for admin UI.
     */
    public function crawlOverview(): JsonResponse
    {
        $all = JobSource::query()
            ->with(['endpoints' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->withCount(['endpoints', 'jobPosts'])
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $dispatchable = $all->filter(fn (JobSource $s) => $s->allowsAutomaticCrawl());

        $mapSource = function (JobSource $source, bool $includeEndpoints = true): array {
            $row = $this->serializeSource($source, $includeEndpoints);
            $row['is_dispatchable'] = $source->allowsAutomaticCrawl();
            $row['search_urls'] = $source->endpoints
                ->filter(fn ($e) => $e instanceof JobSourceEndpoint && $e->is_enabled)
                ->map(fn (JobSourceEndpoint $e) => $e->url)
                ->values()
                ->all();

            return $row;
        };

        return $this->successResponse([
            'totals' => [
                'all' => $all->count(),
                'enabled' => $all->where('is_enabled', true)->count(),
                'approved' => $all->where('is_approved', true)->count(),
                'whitelisted' => $all->where('is_enabled', true)->where('is_approved', true)->count(),
                'dispatchable' => $dispatchable->count(),
            ],
            'dispatchable_sources' => $dispatchable
                ->map(fn (JobSource $s) => $mapSource($s))
                ->values(),
            'catalog' => $all->map(fn (JobSource $s) => $mapSource($s, false))->values(),
            'ai' => config('aggregation.ai'),
            'default_catalog' => $this->buildDefaultCatalogPayload(),
        ]);
    }

    /**
     * Built-in catalog from config merged with DB load state (for admin toggle/delete).
     */
    public function defaultCatalog(): JsonResponse
    {
        return $this->successResponse($this->buildDefaultCatalogPayload());
    }

    /**
     * @return array{
     *   total: int,
     *   loaded_count: int,
     *   enabled_count: int,
     *   dispatchable_count: int,
     *   items: list<array<string, mixed>>
     * }
     */
    protected function buildDefaultCatalogPayload(): array
    {
        $configSources = config('aggregation.official_sources', []);
        if (! is_array($configSources)) {
            $configSources = [];
        }

        $slugs = collect($configSources)
            ->pluck('slug')
            ->filter(fn ($s) => is_string($s) && $s !== '')
            ->values()
            ->all();

        $dbBySlug = JobSource::query()
            ->withCount(['endpoints', 'jobPosts'])
            ->whereIn('slug', $slugs)
            ->get()
            ->keyBy('slug');

        $items = [];
        foreach ($configSources as $pilot) {
            if (! is_array($pilot) || empty($pilot['slug'])) {
                continue;
            }

            $slug = (string) $pilot['slug'];
            /** @var JobSource|null $db */
            $db = $dbBySlug->get($slug);

            $sourceType = $pilot['source_type'] ?? null;
            $typeEnum = is_string($sourceType)
                ? JobSourceType::tryFrom($sourceType)
                : null;

            $reliability = $pilot['reliability_level'] ?? null;
            $reliabilityEnum = is_string($reliability)
                ? JobSourceReliability::tryFrom($reliability)
                : null;

            $quality = $pilot['quality_status'] ?? JobSourceQualityStatus::ManualOnly->value;
            $qualityEnum = is_string($quality)
                ? JobSourceQualityStatus::tryFrom($quality)
                : null;

            $endpointUrls = collect($pilot['endpoints'] ?? [])
                ->pluck('url')
                ->filter()
                ->values()
                ->all();

            $items[] = [
                'slug' => $slug,
                'name' => (string) ($pilot['name'] ?? $slug),
                'official_url' => (string) ($pilot['official_url'] ?? ''),
                'domain' => (string) ($pilot['domain'] ?? JobSource::extractDomain($pilot['official_url'] ?? null) ?? ''),
                'source_type' => $typeEnum?->value ?? $sourceType,
                'source_type_label' => $typeEnum?->label(),
                'reliability_level' => $reliabilityEnum?->value ?? $reliability,
                'reliability_label' => $reliabilityEnum?->label(),
                'quality_status' => $qualityEnum?->value ?? $quality,
                'quality_status_label' => $qualityEnum?->label() ?? JobSourceQualityStatus::ManualOnly->label(),
                'priority' => (int) ($pilot['priority'] ?? 50),
                'config_endpoints' => $endpointUrls,
                'is_loaded' => $db instanceof JobSource,
                'id' => $db?->id,
                'is_enabled' => $db instanceof JobSource ? (bool) $db->is_enabled : (bool) ($pilot['is_enabled'] ?? false),
                'is_approved' => $db instanceof JobSource ? (bool) $db->is_approved : (bool) ($pilot['is_approved'] ?? false),
                'is_dispatchable' => $db instanceof JobSource ? $db->allowsAutomaticCrawl() : false,
                'endpoints_count' => $db instanceof JobSource
                    ? (int) $db->endpoints_count
                    : count($endpointUrls),
                'job_posts_count' => $db instanceof JobSource ? (int) $db->job_posts_count : 0,
                'can_delete' => $db instanceof JobSource && (int) $db->job_posts_count === 0,
            ];
        }

        usort($items, fn (array $a, array $b) => ($a['priority'] <=> $b['priority']) ?: strcmp($a['name'], $b['name']));

        $loaded = collect($items)->where('is_loaded', true);

        return [
            'total' => count($items),
            'loaded_count' => $loaded->count(),
            'enabled_count' => $loaded->where('is_enabled', true)->count(),
            'dispatchable_count' => $loaded->where('is_dispatchable', true)->count(),
            'items' => $items,
        ];
    }

    /**
     * Disable all loaded default sources (does not delete).
     */
    public function bulkDisableDefaults(): JsonResponse
    {
        $slugs = collect(config('aggregation.official_sources', []))
            ->pluck('slug')
            ->filter(fn ($s) => is_string($s) && $s !== '')
            ->values()
            ->all();

        $updated = JobSource::query()
            ->whereIn('slug', $slugs)
            ->where('is_enabled', true)
            ->update(['is_enabled' => false]);

        return $this->successResponse([
            'disabled' => $updated,
        ], $updated > 0 ? "{$updated} منبع پیش‌فرض غیرفعال شد." : 'منبع فعالی برای غیرفعال‌سازی نبود.');
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
            'default_catalog' => $this->buildDefaultCatalogPayload(),
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

        $run = $result['run']?->fresh(['errors']) ?? $result['run'];
        $freshSource = $result['source'] ?? $source->fresh();
        if (! $freshSource instanceof JobSource) {
            $freshSource = $source;
        }

        $qualityStatus = $freshSource->quality_status;

        return $this->successResponse([
            'summary' => $result['summary'],
            'health' => $result['health'] ?? null,
            'quality_status' => $qualityStatus instanceof \BackedEnum ? $qualityStatus->value : $qualityStatus,
            'quality_status_label' => $qualityStatus instanceof JobSourceQualityStatus
                ? $qualityStatus->label()
                : JobSourceQualityStatus::Active->label(),
            'source' => $this->serializeSource($freshSource),
            'run' => $run instanceof CrawlerRun ? $this->serializeRun($run) : null,
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

        $updated = app(SourceHealthService::class)->resetCounters($source);

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
        if (! $endpoint instanceof JobSourceEndpoint) {
            return $this->errorResponse('Endpoint ایجاد نشد.', 500);
        }

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

        return $this->successResponse($this->serializeEndpoint($endpoint->fresh() ?? $endpoint), 'Endpoint به‌روزرسانی شد.');
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

        $seeder = new PilotJobSourceSeeder;
        $priority = (int) ($data['priority'] ?? ($existing !== null ? $existing->priority : null) ?? PilotJobSourceSeeder::PRIORITY_DEFAULT);

        return [
            'name' => $data['name'],
            'slug' => $slug,
            'official_url' => $data['official_url'],
            'domain' => $domain,
            'source_type' => $data['source_type'],
            'reliability_level' => $data['reliability_level'],
            'priority' => $seeder->normalizePriority($priority),
            'is_enabled' => array_key_exists('is_enabled', $data)
                ? (bool) $data['is_enabled']
                : ($existing !== null ? (bool) $existing->is_enabled : false),
            'is_approved' => array_key_exists('is_approved', $data)
                ? (bool) $data['is_approved']
                : ($existing !== null ? (bool) $existing->is_approved : false),
            'quality_status' => $data['quality_status']
                ?? $this->enumValue($existing !== null ? $existing->quality_status : null)
                ?? JobSourceQualityStatus::Active->value,
            'crawler_type' => $data['crawler_type'],
            'crawl_frequency' => $data['crawl_frequency'] ?? ($existing !== null ? $existing->crawl_frequency : null) ?? 'daily',
            'schedule_mode' => $data['schedule_mode'] ?? ($existing !== null ? $existing->schedule_mode : null) ?? 'global',
            'custom_schedule_times' => $this->normalizeCustomTimes(
                $data['custom_schedule_times'] ?? ($existing !== null ? $existing->custom_schedule_times : null)
            ),
            'notes' => $data['notes'] ?? ($existing !== null ? $existing->notes : null),
            'quality_notes' => array_key_exists('quality_notes', $data)
                ? $data['quality_notes']
                : ($existing !== null ? $existing->quality_notes : null),
        ];
    }

    /**
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

    /**
     * @return array<string, mixed>
     */
    protected function serializeSource(JobSource $source, bool $withEndpoints = false): array
    {
        $sourceType = $source->source_type;
        $reliability = $source->reliability_level;
        $qualityStatus = $source->quality_status ?? JobSourceQualityStatus::Active;
        $crawlerType = $source->crawler_type;
        $lastCrawledAt = $source->last_crawled_at;
        $lastSuccessAt = $source->last_success_at;
        $lastFailureAt = $source->last_failure_at;
        $healthBackoffUntil = $source->health_backoff_until;

        $row = [
            'id' => $source->id,
            'name' => $source->name,
            'slug' => $source->slug,
            'official_url' => $source->official_url,
            'domain' => $source->domain,
            'source_type' => $this->enumValue($sourceType),
            'source_type_label' => $sourceType instanceof JobSourceType ? $sourceType->label() : null,
            'reliability_level' => $this->enumValue($reliability),
            'reliability_label' => $reliability instanceof JobSourceReliability ? $reliability->label() : null,
            'priority' => $source->priority,
            'is_enabled' => (bool) $source->is_enabled,
            'is_approved' => (bool) $source->is_approved,
            'is_whitelisted' => (bool) ($source->is_enabled && $source->is_approved),
            'quality_status' => $this->enumValue($qualityStatus) ?? JobSourceQualityStatus::Active->value,
            'quality_status_label' => $qualityStatus instanceof JobSourceQualityStatus
                ? $qualityStatus->label()
                : JobSourceQualityStatus::Active->label(),
            'allows_automatic_crawl' => $source->allowsAutomaticCrawl(),
            'crawler_type' => $this->enumValue($crawlerType),
            'crawler_type_label' => $crawlerType instanceof JobCrawlerType ? $crawlerType->label() : null,
            'crawl_frequency' => $source->crawl_frequency,
            'schedule_mode' => $source->schedule_mode ?: 'global',
            'custom_schedule_times' => $source->custom_schedule_times,
            'last_crawled_at' => $lastCrawledAt instanceof CarbonInterface ? $lastCrawledAt->toIso8601String() : null,
            'last_success_at' => $lastSuccessAt instanceof CarbonInterface ? $lastSuccessAt->toIso8601String() : null,
            'last_failure_at' => $lastFailureAt instanceof CarbonInterface ? $lastFailureAt->toIso8601String() : null,
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
            'health_backoff_until' => $healthBackoffUntil instanceof CarbonInterface
                ? $healthBackoffUntil->toIso8601String()
                : null,
            'in_backoff' => $healthBackoffUntil instanceof CarbonInterface
                ? $healthBackoffUntil->isFuture()
                : false,
            'endpoints_count' => $source->endpoints_count ?? $source->endpoints()->count(),
            'job_posts_count' => $source->job_posts_count ?? null,
            'crawler_runs_count' => $source->crawler_runs_count ?? null,
            'allows_auto_publish_eligible' => $source->allowsAutoPublish(),
            'created_at' => $source->created_at?->toIso8601String(),
            'updated_at' => $source->updated_at?->toIso8601String(),
        ];

        if ($withEndpoints || $source->relationLoaded('endpoints')) {
            $row['endpoints'] = $source->endpoints
                ->filter(fn ($e) => $e instanceof JobSourceEndpoint)
                ->map(fn (JobSourceEndpoint $e) => $this->serializeEndpoint($e))
                ->values();
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeEndpoint(JobSourceEndpoint $endpoint): array
    {
        $endpointType = $endpoint->endpoint_type;

        return [
            'id' => $endpoint->id,
            'job_source_id' => $endpoint->job_source_id,
            'url' => $endpoint->url,
            'endpoint_type' => $this->enumValue($endpointType),
            'http_method' => $endpoint->http_method,
            'parser_type' => $endpoint->parser_type,
            'is_enabled' => (bool) $endpoint->is_enabled,
            'sort_order' => $endpoint->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeRun(CrawlerRun $run): array
    {
        $status = $run->status;
        $startedAt = $run->started_at;
        $finishedAt = $run->finished_at;

        return [
            'id' => $run->id,
            'job_source_id' => $run->job_source_id,
            'status' => $this->enumValue($status),
            'started_at' => $startedAt instanceof CarbonInterface ? $startedAt->toIso8601String() : null,
            'finished_at' => $finishedAt instanceof CarbonInterface ? $finishedAt->toIso8601String() : null,
            'execution_ms' => $run->execution_ms,
            'jobs_found' => $run->jobs_found,
            'jobs_created' => $run->jobs_created,
            'jobs_updated' => $run->jobs_updated,
            'duplicates' => $run->duplicates,
            'errors_count' => $run->errors_count,
            'meta' => is_array($run->meta) ? $run->meta : null,
            'errors' => $run->relationLoaded('errors')
                ? $run->errors->map(function (CrawlerError $e) {
                    $occurredAt = $e->occurred_at;

                    return [
                        'id' => $e->id,
                        'error_type' => $e->error_type,
                        'message' => $e->message,
                        'url' => $e->url,
                        'occurred_at' => $occurredAt instanceof CarbonInterface
                            ? $occurredAt->toIso8601String()
                            : null,
                        'context' => $this->domains->sanitizeContext(
                            is_array($e->context) ? $e->context : null
                        ),
                    ];
                })->values()->all()
                : [],
        ];
    }

    protected function enumValue(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }
}
