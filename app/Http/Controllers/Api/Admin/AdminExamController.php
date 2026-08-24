<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\ExamStoreRequest;
use App\Http\Requests\Api\ExamUpdateRequest;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamCategory;
use App\Models\JobClassification;
use App\Models\JobPost;
use App\Models\Question;
use App\Services\AuditLogService;
use App\Services\ExamService;
use App\Support\OperatorPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminExamController extends BaseController
{
    public function __construct(
        protected ExamService $examService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:exam_categories,id'],
            'job_classification_id' => ['nullable', 'integer', 'exists:job_classifications,id'],
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

        if (! empty($data['job_classification_id'])) {
            $classId = (int) $data['job_classification_id'];
            $childIds = JobClassification::query()->where('parent_id', $classId)->pluck('id')->all();
            $ids = array_merge([$classId], $childIds);
            $query->whereIn('job_classification_id', $ids);
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

    public function preview(int $id): JsonResponse
    {
        $exam = Exam::query()
            ->with(['classification:id,name'])
            ->find($id);

        if (! $exam) {
            return $this->errorResponse('آزمون یافت نشد.', 404);
        }

        $questions = Question::query()
            ->where('exam_id', $id)
            ->orderBy('id')
            ->get([
                'id', 'question_text', 'question_type',
                'option_a', 'option_b', 'option_c', 'option_d',
                'correct_answer', 'explanation', 'difficulty', 'subject',
            ]);

        return $this->successResponse([
            'exam' => $this->detailItem($exam->loadCount(['questions', 'attempts'])),
            'questions' => $questions,
        ]);
    }

    public function store(ExamStoreRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['created_by'] = $request->user()->id;
        $payload['status'] = $payload['status'] ?? 'published';
        $payload['is_random'] = (bool) ($payload['is_random'] ?? false);
        $payload['random_config'] = $payload['random_config'] ?? null;
        $payload['total_questions'] = $this->totalFromRandomConfig($payload);
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
            $payload['category_id'] = ExamCategory::query()->firstOrCreate(
                ['slug' => 'general'],
                ['name' => 'عمومی', 'icon' => 'book']
            )->id;
        }

        $exam = Exam::query()->create($payload);
        app(AuditLogService::class)->log('exam.created', $exam, null, $exam->only(['title', 'status', 'slug']));

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
        $payload = $request->validated();
        if (array_key_exists('random_config', $payload) || array_key_exists('is_random', $payload)) {
            $isRandom = (bool) ($payload['is_random'] ?? $exam->is_random);
            if ($isRandom) {
                $payload['total_questions'] = $this->totalFromRandomConfig([
                    'is_random' => true,
                    'random_config' => $payload['random_config'] ?? $exam->random_config,
                ]);
            }
        }
        $exam->update($payload);
        app(AuditLogService::class)->log('exam.updated', $exam, $old, $exam->fresh()->only(['title', 'status', 'slug', 'price']));

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

        app(AuditLogService::class)->log('exam.archived', $exam, $exam->only(['title', 'status']), ['status' => 'archived']);
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

    /**
     * Operator/admin practice attempt — works for draft & published, bypasses subscription.
     */
    public function practiceStart(Request $request, int $id): JsonResponse
    {
        $exam = Exam::query()->find($id);

        if (! $exam || $exam->status === 'archived') {
            return $this->errorResponse('آزمون یافت نشد یا بایگانی شده است.', 404);
        }

        $user = $request->user();

        ExamAttempt::query()
            ->where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'in_progress')
            ->update(['status' => 'abandoned', 'finished_at' => now()]);

        $questions = $this->examService->getQuestionsForAttempt($exam, true);

        if ($questions->isEmpty()) {
            return $this->errorResponse('سوالی برای این آزمون تعریف نشده است.', 422);
        }

        $attempt = ExamAttempt::query()->create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'subject' => null,
            'started_at' => now(),
            'finished_at' => null,
            'score' => 0,
            'total_correct' => 0,
            'total_wrong' => 0,
            'status' => 'in_progress',
            'answers' => [],
        ]);

        $ttl = max(60, ((int) $exam->duration_minutes) * 60);
        $this->examService->cacheAttempt($attempt, $questions, $ttl);
        $endsAt = $attempt->started_at->copy()->addMinutes($exam->duration_minutes);

        return $this->successResponse([
            'attempt_id' => $attempt->id,
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'duration_minutes' => $exam->duration_minutes,
                'passing_score' => $exam->passing_score,
                'total_marks' => $exam->total_marks,
                'status' => $exam->status,
            ],
            'questions' => $this->examService->formatQuestionsForTaking($questions),
            'end_time' => $endsAt->timestamp,
            'duration_minutes' => $exam->duration_minutes,
            'per_page' => 5,
        ], 'آزمون‌گیری آغاز شد.', 201);
    }

    public function practiceSubmit(Request $request, int $id, int $attemptId): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'string', 'in:a,b,c,d'],
        ]);

        $user = $request->user();
        $exam = Exam::query()->find($id);
        $attempt = ExamAttempt::query()
            ->whereKey($attemptId)
            ->where('exam_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $exam || ! $attempt) {
            return $this->errorResponse('تلاش آزمون یافت نشد.', 404);
        }

        if ($attempt->status !== 'in_progress') {
            return $this->errorResponse('این تلاش قبلاً ثبت شده است.', 422);
        }

        $answers = $data['answers'] ?? [];
        $cachedAnswers = $this->examService->getAutosavedAnswers($attempt->id);
        if ($cachedAnswers !== []) {
            $answers = array_replace($cachedAnswers, $answers);
        }

        $scoreData = DB::transaction(function () use ($attempt, $answers) {
            $attempt->refresh()->load('exam');
            $questionIds = $this->examService->cachedQuestionIds($attempt->id);
            $questions = Question::query()
                ->where('exam_id', $attempt->exam_id)
                ->when($questionIds !== [], fn ($q) => $q->whereIn('id', $questionIds))
                ->get();

            $scoreData = $this->examService->calculateScore($attempt, $answers, $questions);

            $attempt->update([
                'status' => 'completed',
                'finished_at' => now(),
                'score' => $scoreData['score'],
                'total_correct' => $scoreData['total_correct'],
                'total_wrong' => $scoreData['total_wrong'],
                'answers' => $answers,
            ]);

            $this->examService->forgetAttemptCache($attempt->id);

            try {
                Exam::query()->whereKey($attempt->exam_id)->increment('attempts_count');
            } catch (\Throwable) {
            }

            return $scoreData;
        });

        return $this->successResponse([
            'attempt_id' => $attempt->id,
            'exam_id' => $exam->id,
            'score' => $scoreData['score'],
            'total_correct' => $scoreData['total_correct'],
            'total_wrong' => $scoreData['total_wrong'],
            'percentage' => $scoreData['percentage'],
        ], 'نتیجه آزمون ذخیره شد.');
    }

    public function practiceResult(Request $request, int $id, int $attemptId): JsonResponse
    {
        $user = $request->user();
        $attempt = ExamAttempt::query()
            ->with('exam')
            ->whereKey($attemptId)
            ->where('exam_id', $id)
            ->first();

        if (! $attempt) {
            return $this->errorResponse('نتیجه یافت نشد.', 404);
        }

        if ($attempt->user_id !== $user->id && ! OperatorPermissions::allows($user, 'exams')) {
            return $this->errorResponse('نتیجه یافت نشد.', 404);
        }

        if ($attempt->status !== 'completed') {
            return $this->errorResponse('نتیجه هنوز آماده نیست.', 422);
        }

        $payload = $this->examService->buildAnswerSheet($attempt);
        $analysis = $payload['analysis'];

        return $this->successResponse([
            'attempt' => [
                'id' => $attempt->id,
                'exam_id' => $attempt->exam_id,
                'score' => $attempt->score,
                'total_correct' => $attempt->total_correct,
                'total_wrong' => $attempt->total_wrong,
                'percentage' => $analysis['percentage'],
                'finished_at' => $attempt->finished_at?->toIso8601String(),
                'started_at' => $attempt->started_at?->toIso8601String(),
            ],
            'exam' => [
                'id' => $attempt->exam->id,
                'title' => $attempt->exam->title,
                'passing_score' => $attempt->exam->passing_score,
                'total_marks' => $attempt->exam->total_marks,
            ],
            'analysis' => $analysis,
            'sheet' => $payload['sheet'],
        ]);
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

    /**
     * @return array<string, mixed>
     */
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
            'is_random' => (bool) $exam->is_random,
            'created_at' => $exam->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detailItem(Exam $exam): array
    {
        return array_merge($this->listItem($exam), [
            'job_post_id' => $exam->job_post_id,
            'job_classification_id' => $exam->job_classification_id,
            'classification_name' => $exam->classification?->name,
            'description' => $exam->description,
            'job_post_title' => $exam->jobPost?->title,
            'random_config' => $exam->random_config,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function totalFromRandomConfig(array $payload): int
    {
        if (! ($payload['is_random'] ?? false)) {
            return 0;
        }
        $subjects = $payload['random_config']['subjects'] ?? [];
        if (! is_array($subjects)) {
            return 0;
        }

        return (int) array_sum(array_map('intval', $subjects));
    }
}
