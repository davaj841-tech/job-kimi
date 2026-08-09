<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SubmitExamRequest;
use App\Http\Resources\ExamAttemptResource;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Repositories\ExamRepository;
use App\Services\ExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamAttemptController extends BaseController
{
    public function __construct(
        protected ExamService $examService,
        protected ExamRepository $examRepository,
        protected \App\Actions\Exam\StartExamAttempt $startExamAttempt,
    ) {}

    public function start(Request $request, int $id): JsonResponse
    {
        return $this->beginAttempt($request, $id);
    }

    public function retry(Request $request, int $id): JsonResponse
    {
        return $this->beginAttempt($request, $id);
    }

    public function submit(SubmitExamRequest $request, int $id, int $attemptId): JsonResponse
    {
        $user = $request->user();
        $exam = $this->examRepository->findById($id);
        $attempt = ExamAttempt::query()->whereKey($attemptId)->where('exam_id', $id)->first();

        if (! $exam || ! $attempt || $attempt->user_id !== $user->id) {
            return $this->errorResponse('تلاش آزمون یافت نشد.', 404);
        }

        if ($attempt->status !== 'in_progress') {
            return $this->errorResponse('این تلاش قبلاً ثبت شده است.', 422);
        }

        $answers = $request->validated('answers') ?? [];

        // Prefer redis autosave merge so dropped network packets don't wipe answers
        $cachedAnswers = $this->examService->getAutosavedAnswers($attempt->id);
        if ($cachedAnswers !== []) {
            $answers = array_replace($cachedAnswers, $answers);
        }

        // اگر زمان تمام شده، با پاسخ‌های فعلی اتو-سابمیت می‌شود
        $result = $this->finalizeAttempt($attempt, $answers);

        return $this->successResponse($result, 'آزمون ثبت شد.');
    }

    public function result(Request $request, int $id, int $attemptId): JsonResponse
    {
        $user = $request->user();
        $attempt = ExamAttempt::query()
            ->with('exam')
            ->whereKey($attemptId)
            ->where('exam_id', $id)
            ->first();

        if (! $attempt || ($attempt->user_id !== $user->id && ! in_array($user->role, ['admin', 'operator'], true))) {
            return $this->errorResponse('نتیجه یافت نشد.', 404);
        }

        if ($attempt->status === 'in_progress' && $this->examService->checkExpiry($attempt)) {
            $this->finalizeAttempt($attempt, $attempt->answers ?? []);
            $attempt->refresh()->load('exam');
        }

        if ($attempt->status !== 'completed') {
            return $this->errorResponse('نتیجه هنوز آماده نیست.', 422);
        }

        $questionIds = array_keys($attempt->answers ?? []);
        $cacheIds = $this->examService->cachedQuestionIds($attempt->id);
        $ids = ! empty($cacheIds) ? $cacheIds : $questionIds;

        $questions = Question::query()
            ->where('exam_id', $id)
            ->when(! empty($ids), fn ($q) => $q->whereIn('id', $ids))
            ->get();

        if ($questions->isEmpty()) {
            $questions = Question::query()->where('exam_id', $id)->get();
        }

        $resultQuestions = $questions->map(function (Question $question) use ($attempt) {
            $userAnswer = $attempt->answers[(string) $question->id]
                ?? $attempt->answers[$question->id]
                ?? null;

            return [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'user_answer' => $userAnswer,
                'correct_answer' => $question->correct_answer,
                'is_correct' => $userAnswer !== null && strtolower((string) $userAnswer) === strtolower($question->correct_answer),
                'explanation' => $question->explanation,
            ];
        })->values()->all();

        $totalMarks = (float) ($attempt->exam->total_marks ?: 1);
        $attempt->percentage = round(((float) $attempt->score / $totalMarks) * 100, 2);
        $attempt->rank = $this->examService->calculateRank($attempt);
        $attempt->result_questions = $resultQuestions;

        return $this->successResponse(new ExamAttemptResource($attempt));
    }

    public function retryWrong(Request $request, int $id, int $attemptId): JsonResponse
    {
        $user = $request->user();
        $exam = $this->examRepository->findById($id);
        $previous = ExamAttempt::query()->whereKey($attemptId)->where('exam_id', $id)->first();

        if (! $exam || ! $previous || $previous->user_id !== $user->id) {
            return $this->errorResponse('تلاش قبلی یافت نشد.', 404);
        }

        if ($previous->status !== 'completed') {
            return $this->errorResponse('فقط از تلاش تکمیل‌شده می‌توان سوالات غلط را مرور کرد.', 422);
        }

        if (! $this->examService->isEligible($user, $exam)) {
            return response()->json($this->examService->subscriptionRequiredPayload(), 403);
        }

        if ($this->examRepository->countInProgress($user, $exam) > 0) {
            return $this->errorResponse('یک تلاش ناتمام برای این آزمون وجود دارد.', 422);
        }

        $answers = $previous->answers ?? [];
        $wrongIds = Question::query()
            ->where('exam_id', $exam->id)
            ->get()
            ->filter(function (Question $question) use ($answers) {
                $userAnswer = $answers[(string) $question->id] ?? $answers[$question->id] ?? null;

                return $userAnswer === null || strtolower((string) $userAnswer) !== strtolower($question->correct_answer);
            })
            ->pluck('id')
            ->all();

        if ($wrongIds === []) {
            return $this->errorResponse('سوال غلطی برای مرور وجود ندارد.', 422);
        }

        return $this->beginAttempt($request, $id, $wrongIds, true);
    }

    protected function beginAttempt(Request $request, int $examId, ?array $onlyQuestionIds = null, bool $isRetryWrong = false): JsonResponse
    {
        $user = $request->user();
        $exam = $this->examRepository->findById($examId);

        if (! $exam || $exam->status !== 'published') {
            return $this->errorResponse('آزمون یافت نشد یا منتشر نشده است.', 404);
        }

        $subject = $request->input('subject');
        if (is_string($subject)) {
            $subject = trim($subject) ?: null;
        } else {
            $subject = null;
        }

        try {
            $started = $this->startExamAttempt->handle($user, $exam, $subject, $onlyQuestionIds, $isRetryWrong);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'SUBSCRIPTION_REQUIRED') {
                return response()->json($this->examService->subscriptionRequiredPayload(), 403);
            }
            $status = str_contains($e->getMessage(), 'یافت نشد') ? 404 : 422;

            return $this->errorResponse($e->getMessage(), $status);
        }

        $attempt = $started['attempt'];
        $questions = $started['questions'];

        return $this->successResponse([
            'attempt_id' => $attempt->id,
            'subject' => $subject,
            'questions' => $this->examService->formatQuestionsForTaking($questions),
            'end_time' => $started['ends_at']->timestamp,
            'duration_minutes' => $exam->duration_minutes,
            'is_retry_wrong' => $isRetryWrong,
            'per_page' => 5,
        ], 'آزمون آغاز شد.', 201);
    }

    protected function finalizeAttempt(ExamAttempt $attempt, array $answers): array
    {
        return DB::transaction(function () use ($attempt, $answers) {
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

            $user = $attempt->user;
            if ($user) {
                $user->notify(new \App\Notifications\GenericDatabaseNotification(
                    'exam_completed',
                    'نتیجه آزمون آماده است',
                    'آزمون «'.($attempt->exam?->title ?? 'آزمون').'» تمام شد - نمره: '.$scoreData['score'],
                    '/exams/'.$attempt->exam_id.'/result/'.$attempt->id
                ));
            }

            event(new \App\Events\ExamCompleted($attempt->fresh(['exam', 'user'])));

            return [
                'score' => $scoreData['score'],
                'total_correct' => $scoreData['total_correct'],
                'total_wrong' => $scoreData['total_wrong'],
                'percentage' => $scoreData['percentage'],
            ];
        });
    }
}
