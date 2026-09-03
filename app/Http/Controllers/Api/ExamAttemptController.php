<?php

namespace App\Http\Controllers\Api;

use App\Actions\Exam\StartExamAttempt;
use App\Events\ExamCompleted;
use App\Http\Requests\Api\SubmitExamRequest;
use App\Http\Resources\ExamAttemptResource;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Setting;
use App\Notifications\GenericDatabaseNotification;
use App\Repositories\ExamRepository;
use App\Services\ExamService;
use App\Services\ReportCardPDFService;
use App\Support\OperatorPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamAttemptController extends BaseController
{
    public function __construct(
        protected ExamService $examService,
        protected ExamRepository $examRepository,
        protected StartExamAttempt $startExamAttempt,
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

        if (! $attempt || ($attempt->user_id !== $user->id && ! OperatorPermissions::allows($user, 'exams'))) {
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
            ->when(! empty($ids), fn ($q) => $q->whereIn('id', $ids))
            ->when(empty($ids), fn ($q) => $q->where('exam_id', $id))
            ->get();

        $expected = (int) ($attempt->exam?->total_questions ?: 0);
        if ($questions->isEmpty() || (! $attempt->exam?->is_random && $expected > 0 && $questions->count() < $expected)) {
            $questions = Question::query()->where('exam_id', $id)->get();
        }

        $resultQuestions = $questions->map(function (Question $question) use ($attempt) {
            $answers = $attempt->answers ?? [];
            $qid = (string) $question->id;
            $userAnswer = array_key_exists($qid, $answers)
                ? $answers[$qid]
                : ($answers[$question->id] ?? null);

            return [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'options' => [
                    'a' => $question->option_a,
                    'b' => $question->option_b,
                    'c' => $question->option_c,
                    'd' => $question->option_d,
                ],
                'user_answer' => $userAnswer,
                'correct_answer' => $question->correct_answer,
                'user_answer_label' => ReportCardPDFService::optionLetter($userAnswer),
                'correct_answer_label' => ReportCardPDFService::optionLetter($question->correct_answer),
                'user_answer_text' => ExamService::optionBody($question, $userAnswer),
                'correct_answer_text' => ExamService::optionBody($question, $question->correct_answer),
                'is_correct' => $userAnswer !== null && $userAnswer !== '' && strtolower((string) $userAnswer) === strtolower((string) $question->correct_answer),
                'explanation' => $question->explanation,
            ];
        })->values()->all();

        $attempt->rank = $this->examService->calculateRank($attempt);
        $attempt->result_questions = $resultQuestions;

        return $this->successResponse(new ExamAttemptResource($attempt));
    }

    public function feedback(Request $request, int $id, int $attemptId): JsonResponse
    {
        $user = $request->user();
        $attempt = ExamAttempt::query()
            ->whereKey($attemptId)
            ->where('exam_id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->first();

        if (! $attempt) {
            return $this->errorResponse('تلاش آزمون یافت نشد.', 404);
        }

        if ($this->examService->checkExpiry($attempt)) {
            return $this->errorResponse('زمان آزمون به پایان رسیده است.', 422);
        }

        $data = $request->validate([
            'question_id' => ['required', 'integer', 'min:1'],
            'answer' => ['nullable', 'string', 'max:10'],
        ]);

        $questionId = (int) $data['question_id'];
        $selected = isset($data['answer']) ? strtolower(trim((string) $data['answer'])) : null;
        if ($selected === '') {
            $selected = null;
        }

        // Persist the just-selected answer so feedback works before autosave debounce.
        if ($selected !== null) {
            $answers = $attempt->answers ?? [];
            $answers[(string) $questionId] = $selected;
            $attempt->answers = $answers;
            $attempt->save();
        }

        $payload = $this->examService->questionFeedback($attempt, $questionId, $selected);
        if ($payload === null) {
            return $this->errorResponse('پس از انتخاب پاسخ، توضیح در دسترس است.', 422);
        }

        return $this->successResponse($payload);
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

        $mode = $request->input('mode', 'missed');
        $answers = $previous->answers ?? [];
        $sourceIds = $this->examService->cachedQuestionIds($previous->id);
        if ($sourceIds === []) {
            $sourceIds = array_map('intval', array_keys($answers));
        }
        $wrongIds = Question::query()
            ->where('exam_id', $exam->id)
            ->when($sourceIds !== [], fn ($q) => $q->whereIn('id', $sourceIds))
            ->get()
            ->filter(function (Question $question) use ($answers, $mode) {
                $qid = (string) $question->id;
                $userAnswer = array_key_exists($qid, $answers)
                    ? $answers[$qid]
                    : ($answers[$question->id] ?? null);
                $blank = $userAnswer === null || $userAnswer === '';
                $wrong = ! $blank && strtolower((string) $userAnswer) !== strtolower((string) $question->correct_answer);

                return match ($mode) {
                    'blank' => $blank,
                    'wrong' => $wrong,
                    default => $blank || $wrong,
                };
            })
            ->pluck('id')
            ->all();

        if ($wrongIds === []) {
            $msg = $mode === 'blank'
                ? 'سوال بدون پاسخی برای پاسخ‌دادن وجود ندارد.'
                : 'سوال غلطی برای مرور وجود ندارد.';

            return $this->errorResponse($msg, 422);
        }

        return $this->beginAttempt(
            $request,
            $id,
            $wrongIds,
            true,
            $previous->id,
            in_array($mode, ['blank', 'wrong'], true) ? $mode : 'wrong'
        );
    }

    /**
     * @param  list<int>|null  $onlyQuestionIds
     */
    protected function beginAttempt(Request $request, int $examId, ?array $onlyQuestionIds = null, bool $isRetryWrong = false, ?int $parentAttemptId = null, ?string $retryMode = null): JsonResponse
    {
        $user = $request->user();
        $exam = $this->examRepository->findById($examId);

        if (! $exam || $exam->status !== 'published') {
            return $this->errorResponse('آزمون یافت نشد یا منتشر نشده است.', 404);
        }

        $resume = $request->boolean('resume');
        $restart = $request->boolean('restart');
        $existing = $this->examRepository->findInProgress($user, $exam);

        if ($existing && $this->examService->checkExpiry($existing)) {
            $this->finalizeAttempt($existing, $existing->answers ?? []);
            $existing = null;
        }

        if ($existing && $restart) {
            $existing->update([
                'status' => 'abandoned',
                'finished_at' => now(),
            ]);
            $this->examService->forgetAttemptCache($existing->id);
            $existing = null;
        }

        if ($existing && ($resume || ! $restart)) {
            if (! $resume && ! $restart) {
                $ends = $existing->started_at?->copy()->addMinutes((int) $exam->duration_minutes);

                return $this->errorResponse('یک تلاش ناتمام برای این آزمون وجود دارد.', 409, [
                    'code' => 'IN_PROGRESS',
                    'attempt_id' => $existing->id,
                    'remaining_seconds' => $ends ? max(0, $ends->getTimestamp() - now()->getTimestamp()) : 0,
                ]);
            }

            return $this->resumeAttempt($exam, $existing, $isRetryWrong);
        }

        try {
            $started = $this->startExamAttempt->handle($user, $exam, null, $onlyQuestionIds, $isRetryWrong, $parentAttemptId, $retryMode);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'SUBSCRIPTION_REQUIRED') {
                return response()->json($this->examService->subscriptionRequiredPayload(), 403);
            }
            $status = str_contains($e->getMessage(), 'یافت نشد') ? 404 : 422;

            return $this->errorResponse($e->getMessage(), $status);
        }

        $attempt = $started['attempt'];
        $questions = $started['questions'];
        $perPage = max(1, min(20, (int) Setting::get('exam_questions_per_page', 5)));

        return $this->successResponse([
            'attempt_id' => $attempt->id,
            'subject' => null,
            'questions' => $this->examService->formatQuestionsForTaking($questions),
            'end_time' => $started['ends_at']->timestamp,
            'duration_minutes' => $exam->duration_minutes,
            'is_retry_wrong' => $isRetryWrong,
            'per_page' => $perPage,
            'answers' => $attempt->answers ?? [],
        ], 'آزمون آغاز شد.', 201);
    }

    protected function resumeAttempt(Exam $exam, ExamAttempt $attempt, bool $isRetryWrong = false): JsonResponse
    {
        $ids = $this->examService->cachedQuestionIds($attempt->id);
        $questions = Question::query()
            ->where('exam_id', $exam->id)
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->get();
        if ($questions->isEmpty()) {
            $questions = Question::query()->where('exam_id', $exam->id)->get();
        }
        $ttl = max(60, (int) $exam->duration_minutes * 60);
        $this->examService->cacheAttempt($attempt, $questions, $ttl, $isRetryWrong);
        $ends = $attempt->started_at?->copy()->addMinutes((int) $exam->duration_minutes) ?? now()->addMinutes((int) $exam->duration_minutes);
        $perPage = max(1, min(20, (int) Setting::get('exam_questions_per_page', 5)));

        return $this->successResponse([
            'attempt_id' => $attempt->id,
            'subject' => null,
            'questions' => $this->examService->formatQuestionsForTaking($questions),
            'end_time' => $ends->timestamp,
            'duration_minutes' => $exam->duration_minutes,
            'is_retry_wrong' => $isRetryWrong,
            'per_page' => $perPage,
            'answers' => $attempt->answers ?? [],
            'resumed' => true,
        ], 'ادامه آزمون.');
    }

    /**
     * @param  array<int|string, mixed>  $answers
     * @return array<string, mixed>
     */
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

            foreach ($questions as $question) {
                $qid = (string) $question->id;
                if (! array_key_exists($qid, $answers) && ! array_key_exists($question->id, $answers)) {
                    $answers[$qid] = null;
                }
            }

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
                $user->notify(new GenericDatabaseNotification(
                    'exam_completed',
                    'نتیجه آزمون آماده است',
                    'آزمون «'.(($attempt->exam !== null ? $attempt->exam->title : null) ?? 'آزمون').'» تمام شد - نمره: '.$scoreData['score'],
                    '/exams/'.$attempt->exam_id.'/result/'.$attempt->id
                ));
            }

            event(new ExamCompleted($attempt->fresh(['exam', 'user'])));

            return [
                'score' => $scoreData['score'],
                'total_correct' => $scoreData['total_correct'],
                'total_wrong' => $scoreData['total_wrong'],
                'total_unanswered' => $scoreData['total_unanswered'],
                'percentage' => $scoreData['percentage'],
            ];
        });
    }
}
