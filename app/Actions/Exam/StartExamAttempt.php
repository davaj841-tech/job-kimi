<?php

namespace App\Actions\Exam;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Repositories\ExamRepository;
use App\Services\ExamService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Creates an in-progress exam attempt and caches question set.
 */
class StartExamAttempt
{
    public function __construct(
        protected ExamService $exams,
        protected ExamRepository $examRepository,
    ) {}

    /**
     * @param  list<int>|null  $onlyQuestionIds
     * @return array{attempt: ExamAttempt, questions: Collection<int, Question>, ends_at: Carbon}
     */
    public function handle(
        User $user,
        Exam $exam,
        ?string $subject = null,
        ?array $onlyQuestionIds = null,
        bool $isRetryWrong = false,
        ?int $parentAttemptId = null,
        ?string $retryMode = null,
    ): array {
        if ($exam->status !== 'published') {
            throw new RuntimeException('آزمون یافت نشد یا منتشر نشده است.');
        }

        if (! $this->exams->isEligible($user, $exam)) {
            throw new RuntimeException('SUBSCRIPTION_REQUIRED');
        }

        if ($this->examRepository->countInProgress($user, $exam) > 0) {
            throw new RuntimeException('یک تلاش ناتمام برای این آزمون وجود دارد.');
        }

        $questions = $this->exams->getQuestionsForAttempt($exam, true, $onlyQuestionIds, $subject);
        if ($questions->isEmpty()) {
            throw new RuntimeException(
                $subject ? 'برای این درس سوالی تعریف نشده است.' : 'سوالی برای این آزمون تعریف نشده است.'
            );
        }

        $attempt = ExamAttempt::query()->create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'subject' => $subject,
            'started_at' => now(),
            'finished_at' => null,
            'score' => 0,
            'total_correct' => 0,
            'total_wrong' => 0,
            'status' => 'in_progress',
            'answers' => [],
            'is_retry_wrong' => $isRetryWrong,
            'parent_attempt_id' => $parentAttemptId,
            'retry_mode' => $retryMode,
        ]);

        $ttl = max(60, $exam->duration_minutes * 60);
        $this->exams->cacheAttempt($attempt, $questions, $ttl, $isRetryWrong);

        /** @var Carbon $startedAt */
        $startedAt = $attempt->started_at;

        return [
            'attempt' => $attempt,
            'questions' => $questions,
            'ends_at' => $startedAt->copy()->addMinutes($exam->duration_minutes),
        ];
    }
}
