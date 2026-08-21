<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AnswerSheetRequest;
use App\Http\Requests\Api\AutosaveExamAnswersRequest;
use App\Models\ExamAttempt;
use App\Services\ExamService;
use App\Services\ReportCardPDFService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AnswerSheetController extends BaseController
{
    public function __construct(
        protected ExamService $examService,
        protected ReportCardPDFService $reportCardPDFService
    ) {}

    public function autosave(AutosaveExamAnswersRequest $request, int $id, int $attemptId): JsonResponse
    {
        $user = $request->user();
        $attempt = $this->findOwnedInProgressAttempt($user->id, $id, $attemptId);

        if (! $attempt) {
            return $this->errorResponse('تلاش آزمون یافت نشد یا قابل ذخیره نیست.', 404);
        }

        if ($this->examService->checkExpiry($attempt)) {
            return $this->errorResponse('زمان آزمون به پایان رسیده است.', 422);
        }

        $answers = $request->validated('answers');
        $this->examService->autosaveAnswers($attempt, $answers);

        // Keep DB answers in sync as soft backup during exam
        $attempt->update(['answers' => $answers]);

        return $this->successResponse([
            'attempt_id' => $attempt->id,
            'saved_at' => now()->toIso8601String(),
            'answers_count' => count(array_filter($answers, fn ($v) => $v !== null && $v !== '')),
        ], 'پاسخ‌ها ذخیره شد.');
    }

    public function autosaved(AnswerSheetRequest $request, int $id, int $attemptId): JsonResponse
    {
        $user = $request->user();
        $attempt = ExamAttempt::query()
            ->whereKey($attemptId)
            ->where('exam_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $attempt) {
            return $this->errorResponse('تلاش آزمون یافت نشد.', 404);
        }

        $answers = $this->examService->getAutosavedAnswers($attempt->id);
        if ($answers === [] && is_array($attempt->answers)) {
            $answers = $attempt->answers;
        }

        return $this->successResponse([
            'attempt_id' => $attempt->id,
            'answers' => $answers,
            'status' => $attempt->status,
        ]);
    }

    public function show(AnswerSheetRequest $request, int $id, int $attemptId): JsonResponse
    {
        $attempt = $this->findReadableCompletedAttempt($request, $id, $attemptId);

        if (! $attempt) {
            return $this->errorResponse('پاسخبرگ یافت نشد.', 404);
        }

        $payload = $this->examService->buildAnswerSheet($attempt);

        return $this->successResponse([
            'attempt_id' => $attempt->id,
            'exam' => [
                'id' => $attempt->exam->id,
                'title' => $attempt->exam->title,
                'total_marks' => $attempt->exam->total_marks,
                'passing_score' => $attempt->exam->passing_score,
                'has_negative_marking' => (bool) $attempt->exam->has_negative_marking,
            ],
            'analysis' => $payload['analysis'],
            'sheet' => $payload['sheet'],
        ]);
    }

    public function reportCard(AnswerSheetRequest $request, int $id, int $attemptId): Response
    {
        $attempt = $this->findReadableCompletedAttempt($request, $id, $attemptId);

        if (! $attempt) {
            return response()->json([
                'success' => false,
                'message' => 'کارنامه یافت نشد.',
                'errors' => null,
            ], 404);
        }

        return $this->reportCardPDFService->download($attempt);
    }

    protected function findOwnedInProgressAttempt(int $userId, int $examId, int $attemptId): ?ExamAttempt
    {
        return ExamAttempt::query()
            ->with('exam')
            ->whereKey($attemptId)
            ->where('exam_id', $examId)
            ->where('user_id', $userId)
            ->where('status', 'in_progress')
            ->first();
    }

    protected function findReadableCompletedAttempt(AnswerSheetRequest $request, int $examId, int $attemptId): ?ExamAttempt
    {
        $user = $request->user();

        $attempt = ExamAttempt::query()
            ->with(['exam', 'user'])
            ->whereKey($attemptId)
            ->where('exam_id', $examId)
            ->first();

        if (! $attempt) {
            return null;
        }

        $isOwner = $attempt->user_id === $user->id;
        $isStaff = \App\Support\OperatorPermissions::allows($user, 'exams');

        if (! $isOwner && ! $isStaff) {
            return null;
        }

        if ($attempt->status !== 'completed') {
            return null;
        }

        return $attempt;
    }
}
