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
            'category_id', 'is_free', 'search', 'status', 'per_page',
            'price_min', 'price_max', 'duration_min', 'duration_max',
            'questions_min', 'questions_max', 'sort',
        ]);
        $filters['status'] = $filters['status'] ?? 'published';
        $filters['user_id'] = $request->user()?->id;
        if ($request->filled('subjects')) {
            $filters['subjects'] = is_array($request->subjects)
                ? $request->subjects
                : explode(',', (string) $request->subjects);
        }

        $exams = $this->examRepository->getPublished($filters);

        return $this->successResponse(new ExamCollection($exams));
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

        return $this->successResponse(new ExamResource($exam));
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
