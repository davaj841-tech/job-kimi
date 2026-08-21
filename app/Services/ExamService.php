<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSubject;
use App\Models\Question;
use App\Models\User;
use App\Repositories\ExamRepository;
use Illuminate\Support\Carbon;
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

        /** @var Carbon $startedAt */
        $startedAt = $attempt->started_at;
        $endsAt = $startedAt->copy()->addMinutes($attempt->exam->duration_minutes);

        return now()->greaterThan($endsAt);
    }

    /**
     * @param  list<int>|null  $onlyIds
     * @return Collection<int, Question>
     */
    public function getQuestionsForAttempt(Exam $exam, bool $shuffle = true, ?array $onlyIds = null, ?string $subject = null): Collection
    {
        if ($onlyIds !== null) {
            $questions = Question::query()->whereIn('id', $onlyIds)->get();

            return $shuffle ? $questions->shuffle()->values() : $questions->values();
        }

        if ($exam->is_random) {
            return app(\App\Services\Exam\RandomExamAssembler::class)->assemble($exam, $subject);
        }

        $query = $exam->questions();

        if ($subject) {
            $query->where('subject', $subject);
        }

        $questions = $query->get();

        return $shuffle ? $questions->shuffle()->values() : $questions->values();
    }

    /**
     * @param  array<int|string, string|null>  $answers
     * @param  Collection<int, Question>|null  $questions
     * @return array{score: float, total_correct: int, total_wrong: int, percentage: float, total_unanswered: int}
     */
    public function calculateScore(ExamAttempt $attempt, array $answers, ?Collection $questions = null): array
    {
        $attempt->loadMissing('exam');
        if ($questions === null) {
            $cacheIds = $this->cachedQuestionIds($attempt->id);
            if (! empty($cacheIds)) {
                $questions = Question::query()->whereIn('id', $cacheIds)->get()->keyBy('id');
            } else {
                $questions = Question::query()
                    ->where('exam_id', $attempt->exam_id)
                    ->get()
                    ->keyBy('id');
            }
        } else {
            $questions = $questions->keyBy('id');
        }

        if ($questions->isEmpty() && ! $attempt->exam?->is_random) {
            $questions = Question::query()->where('exam_id', $attempt->exam_id)->get()->keyBy('id');
        }

        $totalQuestions = max($questions->count(), 1);
        $correct = 0;
        $wrong = 0;
        $unanswered = 0;
        $exam = $attempt->exam;
        $hasNegative = (bool) ($exam?->has_negative_marking);

        foreach ($questions as $question) {
            $qid = (string) $question->id;
            $userAnswer = array_key_exists($qid, $answers)
                ? $answers[$qid]
                : ($answers[$question->id] ?? null);

            if ($userAnswer === null || $userAnswer === '') {
                $unanswered++;

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
            // بی‌پاسخ نمره نمی‌گیرد ولی غلط هم حساب نمی‌شود
            $answeredCorrectRatio = $totalQuestions > 0 ? ($correct / $totalQuestions) : 0;
            $score = round($answeredCorrectRatio * $totalMarks, 2);
            $percentage = round($answeredCorrectRatio * 100, 2);
        }

        return [
            'score' => $score,
            'total_correct' => $correct,
            'total_wrong' => $wrong,
            'total_unanswered' => $unanswered,
            'percentage' => $percentage,
        ];
    }

    /**
     * @param  array<int|string, string|null>  $answers
     */
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

    /**
     * @return array<int|string, string|null>
     */
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
     * @return array{
     *     attempt: ExamAttempt,
     *     sheet: list<array<string, mixed>>,
     *     analysis: array<string, mixed>
     * }
     */
    public function buildAnswerSheet(ExamAttempt $attempt): array
    {
        $attempt->loadMissing(['exam', 'user']);

        $questionIds = $this->cachedQuestionIds($attempt->id);
        $answerKeys = array_map('intval', array_keys($attempt->answers ?? []));
        $ids = $questionIds !== [] ? $questionIds : $answerKeys;

        $questions = Question::query()
            ->where('exam_id', $attempt->exam_id)
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->get();

        $expected = (int) ($attempt->exam?->total_questions ?: 0);
        if ($questions->isEmpty() || (! $attempt->exam?->is_random && $expected > 0 && $questions->count() < $expected)) {
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
            $qid = (string) $question->id;
            $userAnswer = array_key_exists($qid, $answers)
                ? $answers[$qid]
                : ($answers[$question->id] ?? null);
            $isCorrect = $userAnswer !== null && $userAnswer !== ''
                && strtolower((string) $userAnswer) === strtolower((string) $question->correct_answer);
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
                'user_answer_label' => \App\Services\ReportCardPDFService::optionLetter($userAnswer),
                'correct_answer_label' => \App\Services\ReportCardPDFService::optionLetter($question->correct_answer),
                'user_answer_text' => self::optionBody($question, $userAnswer),
                'correct_answer_text' => self::optionBody($question, $question->correct_answer),
                'is_correct' => $isCorrect,
                'is_blank' => $isBlank,
                'explanation' => $question->explanation,
                'subject' => $question->subject,
                'difficulty' => $question->difficulty,
            ];

            $subject = $question->subject ?: 'general';
            if (! isset($bySubject[$subject])) {
                $bySubject[$subject] = [
                    'subject' => $subject,
                    'subject_label' => self::subjectDisplayName($subject),
                    'correct' => 0,
                    'wrong' => 0,
                    'blank' => 0,
                    'total' => 0,
                ];
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

        $labels = ExamSubject::query()
            ->whereIn('slug', array_keys($bySubject))
            ->pluck('name', 'slug');

        foreach ($bySubject as $slug => &$row) {
            $row['subject_label'] = self::subjectDisplayName($slug, $labels[$slug] ?? null);
            $row['percentage'] = $row['total'] > 0
                ? round(($row['correct'] / $row['total']) * 100, 1)
                : 0;
        }
        unset($row);

        $correct = 0;
        $wrong = 0;
        $blank = 0;
        foreach ($sheet as $row) {
            if ($row['is_blank']) {
                $blank++;
            } elseif ($row['is_correct']) {
                $correct++;
            } else {
                $wrong++;
            }
        }
        $totalQuestions = max(count($sheet), 1);
        $percentage = round(($correct / $totalQuestions) * 100, 2);

        return [
            'attempt' => $attempt,
            'sheet' => $sheet,
            'analysis' => [
                'score' => $attempt->score,
                'percentage' => $percentage,
                'total_correct' => $correct,
                'total_wrong' => $wrong,
                'total_unanswered' => $blank,
                'total_questions' => count($sheet),
                'rank' => $this->calculateRank($attempt),
                'by_subject' => array_values($bySubject),
                'has_negative_marking' => (bool) ($attempt->exam->has_negative_marking),
                'negative_mark_ratio' => (float) ($attempt->exam->negative_mark_ratio ?? 0.3333),
                'passed' => (float) $attempt->score >= (float) $attempt->exam->passing_score,
                'is_retry_wrong' => (bool) $attempt->is_retry_wrong,
                'retry_mode' => $attempt->retry_mode,
                'parent_attempt_id' => $attempt->parent_attempt_id,
            ],
        ];
    }

    /**
     * @param  Collection<int, Question>  $questions
     */
    public function cacheAttempt(ExamAttempt $attempt, Collection $questions, int $ttlSeconds, bool $isRetryWrong = false): void
    {
        /** @var Carbon $startedAt */
        $startedAt = $attempt->started_at;

        Cache::put($this->cacheKey($attempt->id), [
            'attempt_id' => $attempt->id,
            'user_id' => $attempt->user_id,
            'exam_id' => $attempt->exam_id,
            'question_ids' => $questions->pluck('id')->values()->all(),
            'ends_at' => $startedAt->copy()->addSeconds($ttlSeconds)->timestamp,
            'is_retry_wrong' => $isRetryWrong,
        ], $ttlSeconds);
    }

    public function forgetAttemptCache(int $attemptId): void
    {
        Cache::forget($this->cacheKey($attemptId));
        $this->forgetAutosave($attemptId);
    }

    /**
     * @return list<int>
     */
    public function cachedQuestionIds(int $attemptId): array
    {
        $data = Cache::get($this->cacheKey($attemptId));

        return is_array($data) ? ($data['question_ids'] ?? []) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAttemptCache(int $attemptId): ?array
    {
        $data = Cache::get($this->cacheKey($attemptId));

        return is_array($data) ? $data : null;
    }

    /**
     * @param  Collection<int, Question>  $questions
     * @return list<array<string, mixed>>
     */
    public function formatQuestionsForTaking(Collection $questions): array
    {
        $slugs = $questions->pluck('subject')->filter()->unique()->values()->all();
        $labels = ExamSubject::query()
            ->whereIn('slug', $slugs)
            ->pluck('name', 'slug')
            ->all();

        return $questions->map(function (Question $q) use ($labels) {
            $slug = $q->subject ?: 'general';

            return [
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
                'subject' => $slug,
                'subject_name' => self::subjectDisplayName($slug, $labels[$slug] ?? null),
            ];
        })->values()->all();
    }

    /**
     * نام فارسی درس برای کارنامه و تحلیل (اسلاگ انگلیسی هرگز به کاربر نشان داده نشود).
     */
    public static function subjectDisplayName(?string $slug, ?string $label = null): string
    {
        $label = trim((string) $label);
        if ($label !== '' && preg_match('/[^\x00-\x7F]/u', $label)) {
            return $label;
        }

        $map = [
            'islamic' => 'معارف',
            'literature' => 'ادبیات',
            'math' => 'ریاضی',
            'mathematics' => 'ریاضی',
            'chemistry' => 'شیمی',
            'physics' => 'فیزیک',
            'iq' => 'هوش',
            'intelligence' => 'هوش',
            'english' => 'انگلیسی',
            'general' => 'عمومی',
            'computer' => 'کامپیوتر',
            'law' => 'حقوق',
            'accounting' => 'حسابداری',
            'management' => 'مدیریت',
        ];

        $key = strtolower(trim((string) ($slug ?: $label)));

        return $map[$key] ?? ($label !== '' ? $label : ($key !== '' ? $key : 'عمومی'));
    }

    public static function optionBody(Question $question, mixed $letter): string
    {
        $key = strtolower(trim((string) $letter));

        return match ($key) {
            'a' => (string) $question->option_a,
            'b' => (string) $question->option_b,
            'c' => (string) $question->option_c,
            'd' => (string) $question->option_d,
            default => '',
        };
    }

    public function cacheKey(int $attemptId): string
    {
        return "exam_attempt:{$attemptId}";
    }

    /**
     * @return array{success: bool, message: string, code: string, errors: null}
     */
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
