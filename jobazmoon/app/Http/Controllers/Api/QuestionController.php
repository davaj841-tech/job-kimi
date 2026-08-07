<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\QuestionStoreRequest;
use App\Http\Requests\Api\QuestionUpdateRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Exam;
use App\Models\Question;
use App\Repositories\QuestionRepository;
use App\Services\QuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionController extends BaseController
{
    public function __construct(
        protected QuestionRepository $questionRepository,
        protected QuestionService $questionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $questions = $this->questionRepository->getFiltered($request->only([
            'exam_id', 'subject', 'difficulty', 'search', 'question_type', 'per_page',
        ]));

        $items = collect($questions->items())->map(function (Question $q) {
            return [
                'id' => $q->id,
                'exam_id' => $q->exam_id,
                'exam_title' => $q->exam?->title,
                'question_text' => $q->question_text,
                'question_type' => $q->question_type,
                'option_a' => $q->option_a,
                'option_b' => $q->option_b,
                'option_c' => $q->option_c,
                'option_d' => $q->option_d,
                'correct_answer' => $q->correct_answer,
                'explanation' => $q->explanation,
                'difficulty' => $q->difficulty,
                'subject' => $q->subject,
                'created_at' => $q->created_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'عملیات موفق',
            'data' => $items,
            'meta' => [
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'per_page' => $questions->perPage(),
                'total' => $questions->total(),
                'from' => $questions->firstItem(),
                'to' => $questions->lastItem(),
            ],
        ]);
    }

    public function show(int $question): JsonResponse
    {
        $model = $this->questionRepository->find($question);

        if (! $model) {
            return $this->errorResponse('سوال یافت نشد.', 404);
        }

        $model->load('exam:id,title');

        return $this->successResponse(new QuestionResource($model));
    }

    public function store(QuestionStoreRequest $request): JsonResponse
    {
        $model = Question::query()->create($request->validated());
        Exam::query()->whereKey($model->exam_id)->increment('total_questions');

        return $this->successResponse(new QuestionResource($model), 'سوال ایجاد شد.', 201);
    }

    public function update(QuestionUpdateRequest $request, int $question): JsonResponse
    {
        $model = $this->questionRepository->find($question);

        if (! $model) {
            return $this->errorResponse('سوال یافت نشد.', 404);
        }

        $model->update($request->validated());

        return $this->successResponse(new QuestionResource($model->fresh()), 'سوال به‌روزرسانی شد.');
    }

    public function destroy(int $question): JsonResponse
    {
        $model = $this->questionRepository->find($question);

        if (! $model) {
            return $this->errorResponse('سوال یافت نشد.', 404);
        }

        $examId = $model->exam_id;
        $model->delete();
        Exam::query()->whereKey($examId)->where('total_questions', '>', 0)->decrement('total_questions');

        return $this->successResponse(null, 'سوال حذف شد.');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $summary = $this->questionService->importFromExcel($request->file('file'));

        return $this->successResponse($summary, 'ورود اکسل انجام شد.');
    }

    public function export(Request $request)
    {
        return $this->questionService->exportToExcel($request->only([
            'exam_id', 'subject', 'difficulty', 'search',
        ]));
    }
}
