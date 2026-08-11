<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\CrawlerError;
use App\Models\CrawlerRun;
use App\Services\Aggregation\JobSourceDomainGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrawlerRunAdminController extends BaseController
{
    public function __construct(
        protected JobSourceDomainGuard $domains,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = CrawlerRun::query()->with(['source:id,name,domain,slug,last_success_at']);

        if ($request->filled('job_source_id')) {
            $query->where('job_source_id', (int) $request->input('job_source_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $runs = $query->orderByDesc('id')->paginate((int) $request->input('per_page', 20));

        return $this->successResponse([
            'data' => collect($runs->items())->map(fn (CrawlerRun $run) => $this->serializeRun($run))->values(),
            'meta' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'per_page' => $runs->perPage(),
                'total' => $runs->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $run = CrawlerRun::query()->with(['source:id,name,domain,slug,last_success_at', 'errors'])->find($id);
        if (! $run) {
            return $this->errorResponse('اجرای خزنده یافت نشد.', 404);
        }

        return $this->successResponse($this->serializeRun($run, true));
    }

    public function errors(Request $request): JsonResponse
    {
        $query = CrawlerError::query()->with(['source:id,name,domain,slug', 'run:id,status,started_at']);

        if ($request->filled('job_source_id')) {
            $query->where('job_source_id', (int) $request->input('job_source_id'));
        }
        if ($request->filled('error_type')) {
            $query->where('error_type', $request->string('error_type')->toString());
        }
        if ($request->filled('crawler_run_id')) {
            $query->where('crawler_run_id', (int) $request->input('crawler_run_id'));
        }

        $errors = $query->orderByDesc('id')->paginate((int) $request->input('per_page', 20));

        return $this->successResponse([
            'data' => collect($errors->items())->map(fn (CrawlerError $error) => [
                'id' => $error->id,
                'job_source_id' => $error->job_source_id,
                'crawler_run_id' => $error->crawler_run_id,
                'source_name' => $error->source?->name,
                'source_domain' => $error->source?->domain,
                'error_type' => $error->error_type,
                'message' => $error->message,
                'url' => $error->url,
                'context' => $this->domains->sanitizeContext($error->context),
                'occurred_at' => $error->occurred_at?->toIso8601String(),
                'run_status' => $error->run?->status?->value,
            ])->values(),
            'meta' => [
                'current_page' => $errors->currentPage(),
                'last_page' => $errors->lastPage(),
                'per_page' => $errors->perPage(),
                'total' => $errors->total(),
            ],
        ]);
    }

    /**
     * Delete failed/partial crawler runs (and related errors) to keep dashboard fast.
     */
    public function pruneFailed(Request $request): JsonResponse
    {
        $aggressive = $request->boolean('aggressive', true);
        $stats = app(\App\Services\SiteAutoHealService::class)->run($aggressive);

        return $this->successResponse($stats, 'وضعیت‌های ناموفق خزش پاک شدند.');
    }

    public function destroy(int $id): JsonResponse
    {
        $run = CrawlerRun::query()->find($id);
        if (! $run) {
            return $this->errorResponse('اجرای خزنده یافت نشد.', 404);
        }

        CrawlerError::query()->where('crawler_run_id', $run->id)->delete();
        $run->delete();

        return $this->successResponse(null, 'اجرا حذف شد.');
    }

    protected function serializeRun(CrawlerRun $run, bool $withErrors = false): array
    {
        $row = [
            'id' => $run->id,
            'job_source_id' => $run->job_source_id,
            'source_name' => $run->source?->name,
            'source_domain' => $run->source?->domain,
            'source_slug' => $run->source?->slug,
            'status' => $run->status?->value,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'execution_ms' => $run->execution_ms,
            'jobs_found' => $run->jobs_found,
            'jobs_created' => $run->jobs_created,
            'jobs_updated' => $run->jobs_updated,
            'duplicates' => $run->duplicates,
            'errors_count' => $run->errors_count,
            'meta' => is_array($run->meta) ? [
                'phase' => $run->meta['phase'] ?? null,
                'http_status' => $run->meta['http_status'] ?? null,
                'rejected' => $run->meta['rejected'] ?? null,
                'validation_errors' => $run->meta['validation_errors'] ?? null,
                'crawl_errors' => $run->meta['crawl_errors'] ?? null,
            ] : null,
            'last_success_at' => $run->source?->last_success_at?->toIso8601String(),
        ];

        if ($withErrors || $run->relationLoaded('errors')) {
            $row['errors'] = $run->errors->map(fn (CrawlerError $e) => [
                'id' => $e->id,
                'error_type' => $e->error_type,
                'message' => $e->message,
                'url' => $e->url,
                'context' => $this->domains->sanitizeContext($e->context),
                'occurred_at' => $e->occurred_at?->toIso8601String(),
            ])->values();
        }

        return $row;
    }
}
