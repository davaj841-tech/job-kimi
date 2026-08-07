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
            'category_id', 'job_classification_id', 'is_free', 'access', 'search', 'status', 'per_page', 'sort',
        ]);
        $filters['status'] = $filters['status'] ?? 'published';
        $filters['user_id'] = $request->user()?->id;

        $exams = $this->examRepository->getPublished($filters);

        return $this->successResponse((new ExamCollection($exams))->resolve());
    }

    public function show(string $slug): JsonResponse
    {
        $exam = $this->examRepository->findBySlug($slug);

        if (! $exam || $exam->status === 'archived') {
            return $this->errorResponse('آزمون یافت نشد.', 404);
        }

        $user = request()->user();
        $exam->user_best_score = $user ? $this->examRepository->userBestScore($user, $exam) : null;
        $exam->is_eligible = $user ? $this->examService->isEligible($user, $exam) : false;

        // دروس موجود در این آزمون
        $subjectSlugs = $exam->questions()->select('subject')->distinct()->pluck('subject')->filter()->values();
        $subjects = \App\Models\ExamSubject::query()
            ->whereIn('slug', $subjectSlugs)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'icon'])
            ->map(function ($s) use ($exam) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'icon' => $s->icon,
                    'question_count' => $exam->questions()->where('subject', $s->slug)->count(),
                ];
            })
            ->values();

        // اگر درسی در جدول نبود ولی روی سوالات هست
        foreach ($subjectSlugs as $slugVal) {
            if ($subjects->firstWhere('slug', $slugVal)) {
                continue;
            }
            $subjects->push([
                'id' => null,
                'name' => $slugVal,
                'slug' => $slugVal,
                'icon' => '📘',
                'question_count' => $exam->questions()->where('subject', $slugVal)->count(),
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
        $data['status'] = $data['status'] ?? 'draft';
        $data['total_questions'] = 0;
        $data['price'] = $data['price'] ?? 0;

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
