<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Repositories\ExamRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ExamService
{
    public function __construct(
        protected ExamRepository $examRepository
    ) {}

    public function isEligible(User $user, Exam $exam): bool
    {
        if ($exam->is_free) {
            return true;
        }

        return match ($exam->subscription_required) {
            'free' => true,
            'any' => true,
            // اشتراک پولی فعال — middleware هم انقضا را پاکسازی می‌کند
            'paid' => app(SubscriptionService::class)->isActive($user),
            default => false,
        };
    }

    public function calculateRank(ExamAttempt $attempt): int
    {
        $betterOrEarlier = ExamAttempt::query()
            ->where('exam_id', $attempt->exam_id)
            ->where('status', 'completed')
            ->where(function ($q) use ($attempt) {
                $q->where('score', '>', $attempt->score)
                    ->orWhere(function ($q2) use ($attempt) {
                        $q2->where('score', $attempt->score)
                            ->where('finished_at', '<', $attempt->finished_at);
                    });
            })
            ->count();

        return $betterOrEarlier + 1;
    }

    public function checkExpiry(ExamAttempt $attempt): bool
    {
        $attempt->loadMissing('exam');

        if ($attempt->status !== 'in_progress' || ! $attempt->started_at || ! $attempt->exam) {
            return false;
        }

        $endsAt = $attempt->started_at->copy()->addMinutes($attempt->exam->duration_minutes);

        return now()->greaterThan($endsAt);
    }

    public function getQuestionsForAttempt(Exam $exam, bool $shuffle = true, ?array $onlyIds = null): Collection
    {
        $query = $exam->questions();

        if ($onlyIds !== null) {
            $query->whereIn('id', $onlyIds);
        }

        $questions = $query->get();

        return $shuffle ? $questions->shuffle()->values() : $questions->values();
    }

    /**
     * @return array{score: float, total_correct: int, total_wrong: int, percentage: float, total_unanswered: int}
     */
    public function calculateScore(ExamAttempt $attempt, array $answers, ?Collection $questions = null): array
    {
        $attempt->loadMissing('exam');
        $questions ??= Question::query()
            ->where('exam_id', $attempt->exam_id)
            ->when(
                ! empty($this->cachedQuestionIds($attempt->id)),
                fn ($q) => $q->whereIn('id', $this->cachedQuestionIds($attempt->id))
            )
            ->get()
            ->keyBy('id');

        if ($questions->isEmpty()) {
            $questions = Question::query()->where('exam_id', $attempt->exam_id)->get()->keyBy('id');
        } else {
            $questions = $questions->keyBy('id');
        }

        $totalQuestions = max($questions->count(), 1);
        $correct = 0;
        $wrong = 0;
        $unanswered = 0;
        $exam = $attempt->exam;
        $hasNegative = (bool) ($exam?->has_negative_marking);

        foreach ($questions as $question) {
            $userAnswer = $answers[(string) $question->id] ?? $answers[$question->id] ?? null;

            if ($userAnswer === null || $userAnswer === '') {
                if ($hasNegative) {
                    $unanswered++;
                } else {
                    $wrong++;
                }
                continue;
            }

            if (strtolower((string) $userAnswer) === strtolower($question->correct_answer)) {
                $correct++;
            } else {
                $wrong++;
            }
        }

        $totalMarks = (float) ($exam->total_marks ?: $totalQuestions);
        $marksPerQuestion = $totalMarks / $totalQuestions;

        if ($hasNegative) {
            $ratio = (float) ($exam->negative_mark_ratio ?? 0.3333);
            $score = ($correct * $marksPerQuestion) - ($wrong * $ratio * $marksPerQuestion);
            $score = round(max(0, $score), 2);
            $percentage = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 2) : 0;
        } else {
            $score = round(($correct / $totalQuestions) * $totalMarks, 2);
            $percentage = round(($correct / $totalQuestions) * 100, 2);
        }

        return [
            'score' => $score,
            'total_correct' => $correct,
            'total_wrong' => $wrong,
            'total_unanswered' => $unanswered,
            'percentage' => $percentage,
        ];
    }

    public function autosaveAnswers(ExamAttempt $attempt, array $answers, ?int $ttlSeconds = null): void
    {
        $attempt->loadMissing('exam');
        $ttl = $ttlSeconds ?? max(60, ((int) ($attempt->exam?->duration_minutes ?? 60)) * 60 + 600);

        Cache::put($this->autosaveKey($attempt->id), [
            'answers' => $answers,
            'saved_at' => now()->toIso8601String(),
        ], $ttl);

        // Merge into main attempt cache payload if present
        $cached = $this->getAttemptCache($attempt->id);
        if ($cached !== null) {
            $cached['answers'] = $answers;
            $cached['answers_saved_at'] = now()->toIso8601String();
            Cache::put($this->cacheKey($attempt->id), $cached, $ttl);
        }
    }

    public function getAutosavedAnswers(int $attemptId): array
    {
        $data = Cache::get($this->autosaveKey($attemptId));
        if (is_array($data) && isset($data['answers']) && is_array($data['answers'])) {
            return $data['answers'];
        }

        $cached = $this->getAttemptCache($attemptId);
        if (is_array($cached) && isset($cached['answers']) && is_array($cached['answers'])) {
            return $cached['answers'];
        }

        return [];
    }

    public function autosaveKey(int $attemptId): string
    {
        return "exam_attempt_autosave:{$attemptId}";
    }

    public function forgetAutosave(int $attemptId): void
    {
        Cache::forget($this->autosaveKey($attemptId));
    }

    /**
     * Build answer-sheet + per-question analysis for a completed attempt.
     *
     * @return array{attempt: ExamAttempt, sheet: array<int, array>, analysis: array}
     */
    public function buildAnswerSheet(ExamAttempt $attempt): array
    {
        $attempt->loadMissing(['exam', 'user']);

        $questionIds = $this->cachedQuestionIds($attempt->id);
        $answerKeys = array_keys($attempt->answers ?? []);

        $questions = Question::query()
            ->where('exam_id', $attempt->exam_id)
            ->when($questionIds !== [], fn ($q) => $q->whereIn('id', $questionIds))
            ->when($questionIds === [] && $answerKeys !== [], fn ($q) => $q->whereIn('id', $answerKeys))
            ->get();

        if ($questions->isEmpty()) {
            $questions = Question::query()->where('exam_id', $attempt->exam_id)->get();
        }

        // Preserve attempt question order when cached
        if ($questionIds !== []) {
            $order = array_flip($questionIds);
            $questions = $questions->sortBy(fn (Question $q) => $order[$q->id] ?? PHP_INT_MAX)->values();
        }

        $answers = $attempt->answers ?? [];
        $bySubject = [];
        $sheet = [];

        foreach ($questions as $index => $question) {
            $userAnswer = $answers[(string) $question->id] ?? $answers[$question->id] ?? null;
            $isCorrect = $userAnswer !== null && $userAnswer !== ''
                && strtolower((string) $userAnswer) === strtolower($question->correct_answer);
            $isBlank = $userAnswer === null || $userAnswer === '';

            $sheet[] = [
                'number' => $index + 1,
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
                'is_correct' => $isCorrect,
                'is_blank' => $isBlank,
                'explanation' => $question->explanation,
                'subject' => $question->subject,
                'difficulty' => $question->difficulty,
            ];

            $subject = $question->subject ?: 'عمومی';
            if (! isset($bySubject[$subject])) {
                $bySubject[$subject] = ['subject' => $subject, 'correct' => 0, 'wrong' => 0, 'blank' => 0, 'total' => 0];
            }
            $bySubject[$subject]['total']++;
            if ($isBlank) {
                $bySubject[$subject]['blank']++;
            } elseif ($isCorrect) {
                $bySubject[$subject]['correct']++;
            } else {
                $bySubject[$subject]['wrong']++;
            }
        }

        $totalMarks = (float) ($attempt->exam->total_marks ?: max($questions->count(), 1));
        $percentage = $totalMarks > 0
            ? round(((float) $attempt->score / $totalMarks) * 100, 2)
            : 0;

        return [
            'attempt' => $attempt,
            'sheet' => $sheet,
            'analysis' => [
                'score' => $attempt->score,
                'percentage' => $percentage,
                'total_correct' => $attempt->total_correct,
                'total_wrong' => $attempt->total_wrong,
                'total_questions' => count($sheet),
                'rank' => $this->calculateRank($attempt),
                'by_subject' => array_values($bySubject),
                'has_negative_marking' => (bool) ($attempt->exam->has_negative_marking),
                'negative_mark_ratio' => (float) ($attempt->exam->negative_mark_ratio ?? 0.3333),
                'passed' => (float) $attempt->score >= (float) $attempt->exam->passing_score,
            ],
        ];
    }

    public function cacheAttempt(ExamAttempt $attempt, Collection $questions, int $ttlSeconds, bool $isRetryWrong = false): void
    {
        Cache::put($this->cacheKey($attempt->id), [
            'attempt_id' => $attempt->id,
            'user_id' => $attempt->user_id,
            'exam_id' => $attempt->exam_id,
            'question_ids' => $questions->pluck('id')->values()->all(),
            'ends_at' => $attempt->started_at->copy()->addSeconds($ttlSeconds)->timestamp,
            'is_retry_wrong' => $isRetryWrong,
        ], $ttlSeconds);
    }

    public function forgetAttemptCache(int $attemptId): void
    {
        Cache::forget($this->cacheKey($attemptId));
        $this->forgetAutosave($attemptId);
    }

    public function cachedQuestionIds(int $attemptId): array
    {
        $data = Cache::get($this->cacheKey($attemptId));

        return is_array($data) ? ($data['question_ids'] ?? []) : [];
    }

    public function getAttemptCache(int $attemptId): ?array
    {
        $data = Cache::get($this->cacheKey($attemptId));

        return is_array($data) ? $data : null;
    }

    public function formatQuestionsForTaking(Collection $questions): array
    {
        return $questions->map(fn (Question $q) => [
            'id' => $q->id,
            'question_text' => $q->question_text,
            'question_type' => $q->question_type,
            'options' => [
                'a' => $q->option_a,
                'b' => $q->option_b,
                'c' => $q->option_c,
                'd' => $q->option_d,
            ],
            'difficulty' => $q->difficulty,
            'subject' => $q->subject,
        ])->values()->all();
    }

    public function cacheKey(int $attemptId): string
    {
        return "exam_attempt:{$attemptId}";
    }

    public function subscriptionRequiredPayload(): array
    {
        return [
            'success' => false,
            'message' => 'برای دسترسی به این آزمون نیاز به اشتراک پولی دارید.',
            'code' => 'SUBSCRIPTION_REQUIRED',
            'errors' => null,
        ];
    }
}
