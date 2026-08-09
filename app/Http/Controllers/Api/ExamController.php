<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ExamStoreRequest;
use App\Http\Requests\Api\ExamUpdateRequest;
use App\Http\Resources\ExamCollection;
use App\Http\Resources\ExamResource;
use App\Models\Exam;
use App\Repositories\ExamRepository;
use App\Services\ExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends BaseController
{
    public function __construct(
        protected ExamRepository $examRepository,
        protected ExamService $examService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'category_id', 'job_classification_id', 'is_free', 'access', 'search', 'per_page', 'sort',
        ]);
        // کاتالوگ عمومی فقط آزمون‌های منتشرشده
        $filters['status'] = 'published';
        $filters['user_id'] = $request->user()?->id;

        $exams = $this->examRepository->getPublished($filters);

        return $this->successResponse((new ExamCollection($exams))->resolve());
    }

    public function show(string $slug): JsonResponse
    {
        $exam = $this->examRepository->findBySlug($slug);

        if (! $exam || $exam->status !== 'published') {
            return $this->errorResponse('آزمون یافت نشد.', 404);
        }

        $user = request()->user();
        $exam->user_best_score = $user ? $this->examRepository->userBestScore($user, $exam) : null;
        $exam->is_eligible = $user ? $this->examService->isEligible($user, $exam) : false;

        // دروس موجود در این آزمون (بدون N+1)
        $countsBySubject = $exam->questions()
            ->selectRaw('subject, COUNT(*) as aggregate')
            ->whereNotNull('subject')
            ->where('subject', '!=', '')
            ->groupBy('subject')
            ->pluck('aggregate', 'subject');

        $subjectSlugs = $countsBySubject->keys()->values();
        $subjects = \App\Models\ExamSubject::query()
            ->whereIn('slug', $subjectSlugs)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'icon'])
            ->map(function ($s) use ($countsBySubject) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'icon' => $s->icon,
                    'question_count' => (int) ($countsBySubject[$s->slug] ?? 0),
                ];
            })
            ->values();

        foreach ($subjectSlugs as $slugVal) {
            if ($subjects->firstWhere('slug', $slugVal)) {
                continue;
            }
            $subjects->push([
                'id' => null,
                'name' => $slugVal,
                'slug' => $slugVal,
                'icon' => '📘',
                'question_count' => (int) ($countsBySubject[$slugVal] ?? 0),
            ]);
        }

        $data = (new ExamResource($exam))->resolve();
        $data['subjects'] = $subjects;

        return $this->successResponse($data);
    }

    public function store(ExamStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 'published';
        $data['total_questions'] = 0;
        $data['price'] = $data['price'] ?? 0;
        $data['has_negative_marking'] = $data['has_negative_marking'] ?? false;
        $data['negative_mark_ratio'] = $data['negative_mark_ratio'] ?? 0.3333;

        if (blank($data['slug'] ?? null)) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['title']).'-'.\Illuminate\Support\Str::random(5);
            if (blank($data['slug'])) {
                $data['slug'] = 'exam-'.\Illuminate\Support\Str::random(8);
            }
        }

        if (empty($data['category_id'])) {
            $data['category_id'] = \App\Models\ExamCategory::query()->firstOrCreate(
                ['slug' => 'general'],
                ['name' => 'عمومی', 'icon' => 'book']
            )->id;
        }

        $exam = Exam::query()->create($data);

        return $this->successResponse(new ExamResource($exam->load('category')->loadCount('questions')), 'آزمون ایجاد شد.', 201);
    }

    public function update(ExamUpdateRequest $request, int $id): JsonResponse
    {
        $exam = $this->examRepository->findById($id);

        if (! $exam) {
            return $this->errorResponse('آزمون یافت نشد.', 404);
        }

        $user = $request->user();
        if (! in_array($user->role, ['admin', 'operator'], true) && $exam->created_by !== $user->id) {
            return $this->errorResponse('دسترسی غیرمجاز.', 403);
        }

        $exam->update($request->validated());

        return $this->successResponse(new ExamResource($exam->fresh()->load('category')->loadCount('questions')), 'آزمون به‌روزرسانی شد.');
    }

    public function destroy(int $id): JsonResponse
    {
        $exam = $this->examRepository->findById($id);

        if (! $exam) {
            return $this->errorResponse('آزمون یافت نشد.', 404);
        }

        // بایگانی به‌جای حذف فیزیکی
        $exam->update(['status' => 'archived']);

        return $this->successResponse(null, 'آزمون بایگانی شد.');
    }
}
