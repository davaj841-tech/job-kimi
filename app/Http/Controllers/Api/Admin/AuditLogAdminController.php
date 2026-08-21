<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\StaffRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogAdminController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->with('user:id,name,mobile,role')->latest('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }
        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->query('action').'%');
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', 'like', '%'.$request->query('entity_type').'%');
        }
        if ($request->filled('role')) {
            $query->whereHas('user', fn ($q) => $q->where('role', $request->query('role')));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        $items = $query->paginate((int) $request->query('per_page', 20));

        return $this->successResponse([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Aggregated operator/admin work report.
     */
    public function report(Request $request): JsonResponse
    {
        $from = $request->query('date_from');
        $to = $request->query('date_to');
        $userId = $request->filled('user_id') ? (int) $request->query('user_id') : null;

        $base = AuditLog::query()->with('user:id,name,mobile,role');
        if ($from) {
            $base->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $base->whereDate('created_at', '<=', $to);
        }
        if ($userId) {
            $base->where('user_id', $userId);
        }

        $byAction = (clone $base)
            ->select('action', DB::raw('COUNT(*) as total'))
            ->groupBy('action')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'action' => $r->action,
                'label' => $this->actionLabel((string) $r->action),
                'total' => (int) $r->total,
            ])
            ->values();

        $byUser = (clone $base)
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(50)
            ->get()
            ->map(function ($r) {
                $user = User::query()->find($r->user_id);

                return [
                    'user_id' => $r->user_id,
                    'name' => $user?->name ?: $user?->mobile ?: ('#'.$r->user_id),
                    'role' => $user?->role,
                    'total' => (int) $r->total,
                ];
            })
            ->values();

        $highlights = [
            'questions_created' => (clone $base)->where('action', 'question.created')->count(),
            'questions_imported' => 0,
            'pdfs_published' => (clone $base)->whereIn('action', ['pdf.created', 'pdf.published', 'pdf.updated'])->count(),
            'blog_posts' => (clone $base)->whereIn('action', ['blog.created', 'blog.updated', 'blog.published'])->count(),
            'exams_managed' => (clone $base)->whereIn('action', ['exam.created', 'exam.updated', 'exam.archived'])->count(),
            'jobs_managed' => (clone $base)->where('action', 'like', 'job.%')->count(),
        ];

        $importRows = (clone $base)->where('action', 'question.imported')->get(['new_values']);
        $importedSum = 0;
        foreach ($importRows as $row) {
            $vals = is_array($row->new_values) ? $row->new_values : [];
            $importedSum += (int) ($vals['created'] ?? $vals['imported'] ?? 1);
        }
        $highlights['questions_imported'] = $importedSum;

        $operators = User::query()
            ->whereIn('role', ['operator', 'admin'])
            ->orderBy('name')
            ->get(['id', 'name', 'mobile', 'role']);

        return $this->successResponse([
            'highlights' => $highlights,
            'by_action' => $byAction,
            'by_user' => $byUser,
            'operators' => $operators,
            'filters' => [
                'date_from' => $from,
                'date_to' => $to,
                'user_id' => $userId,
            ],
        ]);
    }

    /**
     * Admin-only: delete audit logs in a date range.
     */
    public function destroyRange(Request $request): JsonResponse
    {
        if (! StaffRoles::isSuperAdmin($request->user())) {
            return $this->errorResponse('فقط سوپرادمین می‌تواند گزارش‌های حسابرسی را حذف کند.', 403);
        }

        $data = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $query = AuditLog::query()
            ->whereDate('created_at', '>=', $data['date_from'])
            ->whereDate('created_at', '<=', $data['date_to']);

        if (! empty($data['user_id'])) {
            $query->where('user_id', (int) $data['user_id']);
        }

        $deleted = $query->delete();

        app(AuditLogService::class)->log('audit.purged', null, null, [
            'deleted' => $deleted,
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'user_id' => $data['user_id'] ?? null,
        ]);

        return $this->successResponse(['deleted' => $deleted], "{$deleted} رکورد حسابرسی حذف شد.");
    }

    protected function actionLabel(string $action): string
    {
        return match (true) {
            str_starts_with($action, 'question.imported') => 'ورود سوال از اکسل',
            str_starts_with($action, 'question.created') => 'ایجاد سوال',
            str_starts_with($action, 'question.') => 'مدیریت سوال',
            str_starts_with($action, 'pdf.') => 'فایل PDF',
            str_starts_with($action, 'blog.') => 'مطالب وبلاگ',
            str_starts_with($action, 'exam.') => 'آزمون',
            str_starts_with($action, 'job.') => 'آگهی استخدام',
            str_starts_with($action, 'admin.') => 'احراز هویت ادمین',
            default => $action,
        };
    }
}
