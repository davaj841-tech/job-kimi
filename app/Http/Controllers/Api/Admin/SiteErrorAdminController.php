<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\SiteErrorsExport;
use App\Http\Controllers\Api\BaseController;
use App\Models\SiteError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SiteErrorAdminController extends BaseController
{
    public function export(Request $request): BinaryFileResponse
    {
        $rows = $this->filteredQuery($request)->with('user:id,name,username')->limit(5000)->get();
        $fileName = 'site-errors-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new SiteErrorsExport($rows), $fileName);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'level' => ['nullable', 'string', 'max:20'],
            'resolved' => ['nullable', 'in:0,1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $rows = $this->filteredQuery($request)
            ->with('user:id,name,username')
            ->paginate($data['per_page'] ?? 20);

        return $this->successResponse([
            'data' => collect($rows->items())->map(fn (SiteError $e) => [
                'id' => $e->id,
                'level' => $e->level,
                'message' => $e->message,
                'message_fa' => $e->message_fa,
                'exception_class' => $e->exception_class,
                'file' => $e->file,
                'line' => $e->line,
                'url' => $e->url,
                'method' => $e->method,
                'user_name' => $e->user?->name ?: $e->user?->username,
                'occurrences' => $e->occurrences,
                'last_seen_at' => $e->last_seen_at?->toIso8601String(),
                'resolved_at' => $e->resolved_at?->toIso8601String(),
                'created_at' => $e->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $e = SiteError::query()->with('user:id,name,username,mobile')->find($id);
        if (! $e) {
            return $this->errorResponse('خطا یافت نشد.', 404);
        }

        return $this->successResponse($e);
    }

    public function resolve(int $id): JsonResponse
    {
        $e = SiteError::query()->find($id);
        if (! $e) {
            return $this->errorResponse('خطا یافت نشد.', 404);
        }
        $e->update(['resolved_at' => now()]);

        return $this->successResponse(null, 'خطا به‌عنوان حل‌شده علامت خورد.');
    }

    public function destroy(int $id): JsonResponse
    {
        $e = SiteError::query()->find($id);
        if (! $e) {
            return $this->errorResponse('خطا یافت نشد.', 404);
        }
        $e->delete();

        return $this->successResponse(null, 'حذف شد.');
    }

    public function clearResolved(): JsonResponse
    {
        SiteError::query()->whereNotNull('resolved_at')->delete();

        return $this->successResponse(null, 'خطاهای حل‌شده پاک شدند.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<SiteError>
     */
    protected function filteredQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'level' => ['nullable', 'string', 'max:20'],
            'resolved' => ['nullable', 'in:0,1'],
        ]);

        $q = SiteError::query()->latest('last_seen_at');

        if (! empty($data['search'])) {
            $s = $data['search'];
            $q->where(function ($w) use ($s) {
                $w->where('message', 'like', "%{$s}%")
                    ->orWhere('message_fa', 'like', "%{$s}%")
                    ->orWhere('exception_class', 'like', "%{$s}%")
                    ->orWhere('url', 'like', "%{$s}%");
            });
        }

        if (! empty($data['level'])) {
            $q->where('level', $data['level']);
        }

        if (($data['resolved'] ?? null) === '1') {
            $q->whereNotNull('resolved_at');
        } elseif (($data['resolved'] ?? '0') === '0') {
            $q->whereNull('resolved_at');
        }

        return $q;
    }

    public function autoHeal(\Illuminate\Http\Request $request): JsonResponse
    {
        $stats = app(\App\Services\SiteAutoHealService::class)->run($request->boolean('aggressive', false));

        return $this->successResponse($stats, 'خودترمیمی اجرا شد.');
    }
}
