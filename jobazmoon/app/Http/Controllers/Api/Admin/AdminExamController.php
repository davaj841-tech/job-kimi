<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\ExamStoreRequest;
use App\Http\Requests\Api\ExamUpdateRequest;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamCategory;
use App\Models\JobPost;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminExamController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:exam_categories,id'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
            'is_free' => ['nullable'],
            'sort' => ['nullable', Rule::in(['desc', 'asc', 'attempts'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Exam::query()
            ->with(['category:id,name', 'classification:id,name'])
            ->withCount(['questions', 'attempts']);

        if (! empty($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('seo_tag', 'like', "%{$search}%");
            });
        }

        if (! empty($data['category_id'])) {
            $query->where('category_id', $data['category_id']);
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (array_key_exists('is_free', $data) && $data['is_free'] !== null && $data['is_free'] !== '') {
            $query->where('is_free', filter_var($data['is_free'], FILTER_VALIDATE_BOOLEAN));
        }

        match ($data['sort'] ?? 'desc') {
            'asc' => $query->orderBy('created_at', 'asc'),
            'attempts' => $query->orderByDesc('attempts_count'),
            default => $query->orderByDesc('created_at'),
        };

        $paginator = $query->paginate($data['per_page'] ?? 20);

        $rows = collect($paginator->items())->map(fn (Exam $exam) => $this->listItem($exam))->values();

        return response()->json([
            'success' => true,
            'message' => 'عملیات موفق',
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $exam = Exam::query()
            ->with(['category:id,name', 'jobPost:id,title', 'classification:id,name'])
            ->withCount(['questions', 'attempts'])
            ->find($id);

        if (! $exam) {
            return $this->errorResponse('آزمون یافت نشد.', 404);
        }

        return $this->successResponse($this->detailItem($exam));
    }

    public function store(ExamStoreRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['created_by'] = $request->user()->id;
        $payload['status'] = $payload['status'] ?? 'draft';
        $payload['total_questions'] = 0;
        $payload['price'] = $payload['price'] ?? 0;
        $payload['has_negative_marking'] = $payload['has_negative_marking'] ?? false;
        $payload['negative_mark_ratio'] = $payload['negative_mark_ratio'] ?? 0.3333;

        if (blank($payload['slug'] ?? null)) {
            $payload['slug'] = Str::slug($payload['title']).'-'.Str::random(5);
            if (blank($payload['slug'])) {
                $payload['slug'] = 'exam-'.Str::random(8);
            }
        }

        if (empty($payload['category_id'])) {
            $payload['category_id'] = \App\Models\ExamCategory::query()->firstOrCreate(
                ['slug' => 'general'],
                ['name' => 'عمومی', 'icon' => 'book']
            )->id;
        }

        $exam = Exam::query()->create($payload);
        app(\App\Services\AuditLogService::class)->log('exam.created', $exam, null, $exam->only(['title', 'status', 'slug']));

        return $this->successResponse(
            $this->detailItem($exam->fresh()->load(['category', 'classification'])->loadCount(['questions', 'attempts'])),
            'آزمون ایجاد شد.',
            201
        );
    }

    public function update(ExamUpdateRequest $request, int $id): JsonResponse
    {
        $exam = Exam::query()->find($id);

        if (! $exam) {
            return $this->errorResponse('آزمون یافت نشد.', 404);
        }

        $old = $exam->only(['title', 'status', 'slug', 'price']);
        $exam->update($request->validated());
        app(\App\Services\AuditLogService::class)->log('exam.updated', $exam, $old, $exam->fresh()->only(['title', 'status', 'slug', 'price']));

        return $this->successResponse(
            $this->detailItem($exam->fresh()->load(['category', 'classification'])->loadCount(['questions', 'attempts'])),
            'آزمون به‌روزرسانی شد.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $exam = Exam::query()->find($id);

        if (! $exam) {
            return $this->errorResponse('آزمون یافت نشد.', 404);
        }

        app(\App\Services\AuditLogService::class)->log('exam.archived', $exam, $exam->only(['title', 'status']), ['status' => 'archived']);
        $exam->update(['status' => 'archived']);

        return $this->successResponse(null, 'آزمون بایگانی شد.');
    }

    public function stats(int $id): JsonResponse
    {
        $exam = Exam::query()->withCount('questions')->find($id);

        if (! $exam) {
            return $this->errorResponse('آزمون یافت نشد.', 404);
        }

        $completed = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('status', 'completed');

        $totalAttempts = (clone $completed)->count();
        $avgScore = $totalAttempts > 0 ? round((float) (clone $completed)->avg('score'), 2) : 0;
        $passed = (clone $completed)
            ->where('score', '>=', $exam->passing_score)
            ->count();
        $passRate = $totalAttempts > 0 ? round(($passed / $totalAttempts) * 100, 2) : 0;

        $top = ExamAttempt::query()
            ->with('user:id,name,mobile')
            ->where('exam_id', $exam->id)
            ->where('status', 'completed')
            ->orderByDesc('score')
            ->limit(5)
            ->get()
            ->map(fn (ExamAttempt $a) => [
                'user_name' => $a->user?->name ?: '—',
                'mobile' => $a->user?->mobile,
                'score' => $a->score,
                'finished_at' => $a->finished_at?->toIso8601String(),
            ]);

        $subjectBreakdown = Question::query()
            ->where('exam_id', $exam->id)
            ->selectRaw('subject, count(*) as total')
            ->groupBy('subject')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->subject ?: 'general',
                'value' => (int) $row->total,
            ]);

        return $this->successResponse([
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'passing_score' => $exam->passing_score,
                'question_count' => $exam->questions_count,
            ],
            'total_attempts' => $totalAttempts,
            'average_score' => $avgScore,
            'pass_rate' => $passRate,
            'top_participants' => $top,
            'subject_breakdown' => $subjectBreakdown,
        ]);
    }

    public function categories(): JsonResponse
    {
        $items = ExamCategory::query()->orderBy('name')->get(['id', 'name', 'slug']);

        return $this->successResponse($items);
    }

    public function jobPosts(): JsonResponse
    {
        $items = JobPost::query()
            ->where('status', 'approved')
            ->latest()
            ->limit(100)
            ->get(['id', 'title', 'company_name']);

        return $this->successResponse($items);
    }

    protected function listItem(Exam $exam): array
    {
        return [
            'id' => $exam->id,
            'title' => $exam->title,
            'slug' => $exam->slug,
            'seo_tag' => $exam->seo_tag,
            'category_id' => $exam->category_id,
            'category_name' => $exam->classification?->name ?: $exam->category?->name,
            'job_classification_id' => $exam->job_classification_id,
            'question_count' => $exam->questions_count ?? $exam->total_questions,
            'attempt_count' => $exam->attempts_count ?? 0,
            'duration_minutes' => $exam->duration_minutes,
            'passing_score' => $exam->passing_score,
            'total_marks' => $exam->total_marks,
            'is_free' => (bool) $exam->is_free,
            'price' => $exam->price,
            'subscription_required' => $exam->subscription_required,
            'has_negative_marking' => (bool) $exam->has_negative_marking,
            'negative_mark_ratio' => (float) ($exam->negative_mark_ratio ?? 0.3333),
            'status' => $exam->status,
            'created_at' => $exam->created_at?->toIso8601String(),
        ];
    }

    protected function detailItem(Exam $exam): array
    {
        return array_merge($this->listItem($exam), [
            'job_post_id' => $exam->job_post_id,
            'job_classification_id' => $exam->job_classification_id,
            'classification_name' => $exam->classification?->name,
            'description' => $exam->description,
            'job_post_title' => $exam->jobPost?->title,
        ]);
    }
}
