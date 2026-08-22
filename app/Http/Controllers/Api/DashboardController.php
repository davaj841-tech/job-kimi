<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\DashboardResource;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSubject;
use App\Models\Question;
use App\Models\UserLoginSession;
use App\Repositories\ExamRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardController extends BaseController
{
    public function __construct(
        protected ExamRepository $examRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user()->load('subscriptionPlan');

        $completed = ExamAttempt::query()
            ->with('exam:id,title,total_marks,passing_score,total_questions')
            ->where('user_id', $user->id)
            ->where('status', 'completed');

        $completedAttempts = (clone $completed)->latest('finished_at')->latest('id')->get();

        $totalTaken = $completedAttempts->count();
        $totalPassed = $completedAttempts->filter(function (ExamAttempt $attempt) {
            return $attempt->resultSummary()['passed'] === true;
        })->count();

        $avgPercentage = $totalTaken > 0
            ? round($completedAttempts->avg(fn (ExamAttempt $a) => $a->resultSummary()['percentage']), 2)
            : 0;
        $totalCorrect = (int) $completedAttempts->sum('total_correct');
        $totalWrong = (int) $completedAttempts->sum('total_wrong');

        $examsThisWeek = $completedAttempts
            ->filter(fn (ExamAttempt $a) => $a->finished_at && $a->finished_at->gte(now()->subDays(7)))
            ->count();

        $scoreTrend = '';
        if ($completedAttempts->count() >= 2) {
            $latest = (float) $completedAttempts->first()?->resultSummary()['percentage'];
            $previous = (float) $completedAttempts->skip(1)->first()?->resultSummary()['percentage'];
            $delta = round($latest - $previous, 1);
            $scoreTrend = ($delta >= 0 ? '+' : '').$delta.'٪ نسبت به قبل';
        }

        $daysLeft = null;
        if ($user->subscription_expires_at) {
            $daysLeft = max(0, now()->diffInDays($user->subscription_expires_at, false));
            $daysLeft = (int) ceil($daysLeft);
            if ($user->subscription_expires_at->isPast()) {
                $daysLeft = 0;
            }
        }

        $progressChart = $this->buildProgressChart($user->id);

        $recent = $this->examRepository->getUserAttempts($user, 10)
            ->map(fn (ExamAttempt $attempt) => $attempt->toHistoryItem())
            ->values()
            ->all();

        // ۱۲ تلاش اخیر (از جدید به قدیم خوانده شده؛ برای نمودار از قدیم به جدید)
        $examChart = $completedAttempts->take(12)->reverse()->values()->map(function (ExamAttempt $a) {
            $stats = $a->resultSummary();

            return [
                'label' => Str::limit($a->exam?->title ?: 'آزمون', 18),
                'percentage' => $stats['percentage'],
                'score' => (float) $a->score,
                'date' => ($a->finished_at ?? $a->created_at)?->toIso8601String(),
            ];
        })->all();

        $scoreHistory = collect($examChart)->take(-10)->values()->map(fn (array $row) => [
            'exam' => $row['label'],
            'score' => $row['percentage'],
            'date' => $row['date'] ?? null,
        ])->all();

        $examsLastMonth = $completedAttempts
            ->filter(fn (ExamAttempt $a) => $a->finished_at && $a->finished_at->gte(now()->subDays(30)) && $a->finished_at->lt(now()->subDays(7)))
            ->count();
        $examsPrevMonth = $completedAttempts
            ->filter(fn (ExamAttempt $a) => $a->finished_at && $a->finished_at->gte(now()->subDays(60)) && $a->finished_at->lt(now()->subDays(30)))
            ->count();
        $examsChange = $examsLastMonth - $examsPrevMonth;

        $avgChange = 0.0;
        if ($completedAttempts->count() >= 2) {
            $latest = (float) $completedAttempts->first()?->resultSummary()['percentage'];
            $previous = (float) $completedAttempts->skip(1)->first()?->resultSummary()['percentage'];
            $avgChange = round($latest - $previous, 1);
        }

        $studySecondsWeek = $completedAttempts
            ->filter(fn (ExamAttempt $a) => $a->finished_at && $a->finished_at->gte(now()->startOfWeek()))
            ->sum(fn (ExamAttempt $a) => $this->attemptDurationSeconds($a));
        $sessionSecondsWeek = (int) UserLoginSession::query()
            ->where('user_id', $user->id)
            ->where('logged_in_at', '>=', now()->startOfWeek())
            ->sum('duration_seconds');
        $studyHoursWeek = round(($studySecondsWeek + $sessionSecondsWeek) / 3600, 1);

        [$rank, $rankChange] = $this->resolveUserRank($user->id);

        $skillLabels = ['ریاضی', 'زبان', 'کامپیوتر', 'استدلال', 'اطلاعات', 'فنی'];
        $skillScores = $this->normalizeSkillScores($progressChart, $skillLabels);
        $avgSkillScores = $this->buildGlobalSkillAverages(array_column($progressChart, 'subject'));

        $sortedSkills = collect($progressChart)
            ->map(fn (array $row) => ['name' => $row['subject_label'], 'score' => (float) $row['average_score']])
            ->sortByDesc('score')
            ->values();
        $strengths = $sortedSkills->take(3)->values()->all();
        $weaknesses = $sortedSkills->sortBy('score')->take(3)->values()->all();
        $weakest = $weaknesses[0]['name'] ?? 'مهارت ضعیف';
        $weakestScore = (float) ($weaknesses[0]['score'] ?? 0);
        $projectedGain = max(1, (int) round((70 - $weakestScore) * 0.15));
        $suggestion = "با ۲ ساعت تمرین در {$weakest}، نمره کلی +{$projectedGain}٪ می‌شود";

        $timeDistribution = $this->buildTimeDistribution($user->id, $studySecondsWeek, $sessionSecondsWeek);

        $recentActivity = $this->buildRecentActivity($completedAttempts->take(5), $user->id);

        $dailyPlan = $this->buildDailyPlan($weakest, $recent);

        $streak = $this->buildStreak($user->id, $rank);

        $scoreGrowth = '';
        if (count($scoreHistory) >= 2) {
            $first = (float) $scoreHistory[0]['score'];
            $last = (float) $scoreHistory[count($scoreHistory) - 1]['score'];
            $delta = round($last - $first, 1);
            $scoreGrowth = ($delta >= 0 ? '+' : '').$delta.'٪ رشد از اولین آزمون';
        }

        $payload = [
            'user' => [
                'name' => $user->name,
                'mobile' => $user->mobile,
                'email' => $user->email,
                'username' => $user->username,
                'province' => $user->province,
                'wallet_balance' => $user->wallet_balance,
                'subscription_name' => $user->subscriptionPlan?->name,
                'subscription_expires_at' => $user->subscription_expires_at?->toIso8601String(),
                'subscription_days_left' => $daysLeft,
            ],
            'stats' => [
                'total_exams_taken' => $totalTaken,
                'total_exams_passed' => $totalPassed,
                'average_score' => $avgPercentage,
                'total_correct_answers' => $totalCorrect,
                'total_wrong_answers' => $totalWrong,
                'exams_this_week' => $examsThisWeek,
                'score_trend' => $scoreTrend,
            ],
            'progress_chart' => $progressChart,
            'exam_chart' => $examChart,
            'recent_attempts' => $recent,
            'available_exams' => [
                'free_count' => Exam::query()->where('status', 'published')->where('is_free', true)->count(),
                'paid_count' => Exam::query()->where('status', 'published')->where('is_free', false)->count(),
            ],
            'kpis' => [
                'total_exams' => $totalTaken,
                'total_exams_change' => $examsChange,
                'avg_score' => $avgPercentage,
                'avg_score_change' => $avgChange,
                'study_hours' => $studyHoursWeek,
                'rank' => $rank,
                'rank_change' => $rankChange,
            ],
            'score_history' => $scoreHistory,
            'score_growth' => $scoreGrowth,
            'skill_labels' => $skillLabels,
            'skill_scores' => $skillScores,
            'avg_skill_scores' => $avgSkillScores,
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'suggestion' => $suggestion,
            'time_distribution' => $timeDistribution,
            'recent_activity' => $recentActivity,
            'daily_plan' => $dailyPlan,
            'streak' => $streak,
        ];

        return $this->successResponse(new DashboardResource($payload));
    }

    /**
     * @return list<array{subject: string, subject_label: string, average_score: float|int, exam_count: int}>
     */
    public function buildProgressChart(int $userId): array
    {
        $attempts = ExamAttempt::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->where('finished_at', '>=', now()->subDays(30))
            ->get(['id', 'answers', 'exam_id']);

        $subjectStats = [];

        foreach ($attempts as $attempt) {
            $answers = $attempt->answers ?? [];
            if ($answers === []) {
                continue;
            }

            $questionIds = array_map('intval', array_keys($answers));
            $questions = Question::query()->whereIn('id', $questionIds)->get(['id', 'subject', 'correct_answer']);

            foreach ($questions as $question) {
                $subject = $question->subject;
                $userAnswer = $answers[(string) $question->id] ?? $answers[$question->id] ?? null;
                $isCorrect = $userAnswer !== null && strtolower((string) $userAnswer) === strtolower($question->correct_answer);

                if (! isset($subjectStats[$subject])) {
                    $subjectStats[$subject] = ['correct' => 0, 'total' => 0, 'exam_ids' => []];
                }

                $subjectStats[$subject]['total']++;
                $subjectStats[$subject]['correct'] += $isCorrect ? 1 : 0;
                $subjectStats[$subject]['exam_ids'][$attempt->exam_id] = true;
            }
        }

        return collect($subjectStats)->map(function (array $stat, string $subject) {
            $name = ExamSubject::query()->where('slug', $subject)->value('name');
            $label = is_string($name) && $name !== '' ? $name : $subject;

            return [
                'subject' => $subject,
                'subject_label' => $label,
                'average_score' => $stat['total'] > 0 ? round(($stat['correct'] / $stat['total']) * 100, 2) : 0,
                'exam_count' => count($stat['exam_ids']),
            ];
        })->values()->all();
    }

    protected function attemptDurationSeconds(ExamAttempt $attempt): int
    {
        if ($attempt->started_at && $attempt->finished_at) {
            return max(0, (int) $attempt->started_at->diffInSeconds($attempt->finished_at));
        }

        return 45 * 60;
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function resolveUserRank(int $userId): array
    {
        $rows = ExamAttempt::query()
            ->select('user_id', DB::raw('SUM(score) as total_score'))
            ->where('status', 'completed')
            ->groupBy('user_id')
            ->orderByDesc('total_score')
            ->pluck('total_score', 'user_id');

        $rank = 1;
        foreach ($rows as $uid => $_) {
            if ((int) $uid === $userId) {
                break;
            }
            $rank++;
        }

        if (! $rows->has($userId)) {
            return [max($rows->count() + 1, 1), 0];
        }

        $weekRows = ExamAttempt::query()
            ->select('user_id', DB::raw('SUM(score) as total_score'))
            ->where('status', 'completed')
            ->where('finished_at', '>=', now()->subDays(7))
            ->groupBy('user_id')
            ->orderByDesc('total_score')
            ->pluck('user_id')
            ->values();

        $weekRank = $weekRows->search($userId);
        $rankChange = $weekRank === false ? 0 : max(0, $rank - ($weekRank + 1));

        return [$rank, $rankChange];
    }

    /**
     * @param  list<array{subject: string, subject_label: string, average_score: float|int, exam_count: int}>  $progressChart
     * @param  list<string>  $labels
     * @return list<float>
     */
    protected function normalizeSkillScores(array $progressChart, array $labels): array
    {
        $byLabel = collect($progressChart)->keyBy('subject_label');
        $scores = [];
        foreach ($labels as $i => $label) {
            $match = $byLabel->get($label);
            if ($match) {
                $scores[] = (float) $match['average_score'];
                continue;
            }
            $fallback = $progressChart[$i]['average_score'] ?? null;
            $scores[] = $fallback !== null ? (float) $fallback : 0;
        }

        return $scores;
    }

    /**
     * @param  list<string>  $subjects
     * @return list<float>
     */
    protected function buildGlobalSkillAverages(array $subjects): array
    {
        $defaults = [62, 58, 55, 60, 57, 54];
        if ($subjects === []) {
            return array_map('floatval', $defaults);
        }

        $attempts = ExamAttempt::query()
            ->where('status', 'completed')
            ->where('finished_at', '>=', now()->subDays(90))
            ->limit(500)
            ->get(['answers']);

        $stats = [];
        foreach ($attempts as $attempt) {
            $answers = $attempt->answers ?? [];
            if ($answers === []) {
                continue;
            }
            $questionIds = array_map('intval', array_keys($answers));
            $questions = Question::query()->whereIn('id', $questionIds)->get(['id', 'subject', 'correct_answer']);
            foreach ($questions as $question) {
                $subject = $question->subject;
                $userAnswer = $answers[(string) $question->id] ?? $answers[$question->id] ?? null;
                $isCorrect = $userAnswer !== null && strtolower((string) $userAnswer) === strtolower($question->correct_answer);
                if (! isset($stats[$subject])) {
                    $stats[$subject] = ['correct' => 0, 'total' => 0];
                }
                $stats[$subject]['total']++;
                $stats[$subject]['correct'] += $isCorrect ? 1 : 0;
            }
        }

        $labels = ['ریاضی', 'زبان', 'کامپیوتر', 'استدلال', 'اطلاعات', 'فنی'];
        $result = [];
        foreach ($labels as $i => $label) {
            $slug = ExamSubject::query()->where('name', $label)->value('slug');
            $key = is_string($slug) && $slug !== '' ? $slug : ($subjects[$i] ?? null);
            if ($key && isset($stats[$key])) {
                $result[] = round(($stats[$key]['correct'] / $stats[$key]['total']) * 100, 1);
            } else {
                $result[] = (float) $defaults[$i];
            }
        }

        return $result;
    }

    /**
     * @return list<array{label: string, value: float, color: string}>
     */
    protected function buildTimeDistribution(int $userId, int $examSeconds, int $sessionSeconds): array
    {
        $total = max(1, $examSeconds + $sessionSeconds);
        $examHours = round($examSeconds / 3600, 1);
        $otherHours = round($sessionSeconds / 3600, 1);
        $resumeHours = round($otherHours * 0.25, 1);
        $fileHours = round($otherHours * 0.45, 1);
        $blogHours = round($otherHours * 0.30, 1);

        if ($total <= 3600) {
            return [
                ['label' => 'آزمون آنلاین', 'value' => max($examHours, 0.5), 'color' => '#3b82f6'],
                ['label' => 'فایل آموزشی', 'value' => 1.2, 'color' => '#10b981'],
                ['label' => 'رزومه‌ساز', 'value' => 0.8, 'color' => '#ef394e'],
                ['label' => 'مقاله/بلاگ', 'value' => 0.5, 'color' => '#a855f7'],
            ];
        }

        return [
            ['label' => 'آزمون آنلاین', 'value' => $examHours, 'color' => '#3b82f6'],
            ['label' => 'فایل آموزشی', 'value' => $fileHours, 'color' => '#10b981'],
            ['label' => 'رزومه‌ساز', 'value' => $resumeHours, 'color' => '#ef394e'],
            ['label' => 'مقاله/بلاگ', 'value' => $blogHours, 'color' => '#a855f7'],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ExamAttempt>  $attempts
     * @return list<array{icon: string, title: string, meta: string, color: string}>
     */
    protected function buildRecentActivity($attempts, int $userId): array
    {
        $items = $attempts->map(function (ExamAttempt $attempt) {
            $stats = $attempt->resultSummary();
            $when = $attempt->finished_at ?? $attempt->created_at;

            return [
                'icon' => '📝',
                'title' => 'آزمون «'.Str::limit($attempt->exam?->title ?: 'آزمون', 28).'»',
                'meta' => round($stats['percentage']).'٪ · '.$when?->diffForHumans(),
                'color' => $stats['passed'] ? 'emerald' : 'blue',
            ];
        })->values()->all();

        $lastSession = UserLoginSession::query()
            ->where('user_id', $userId)
            ->latest('logged_in_at')
            ->first();

        if ($lastSession) {
            array_unshift($items, [
                'icon' => '🔐',
                'title' => 'ورود به حساب',
                'meta' => $lastSession->logged_in_at?->diffForHumans() ?? '',
                'color' => 'purple',
            ]);
        }

        return array_slice($items, 0, 5);
    }

    /**
     * @param  list<array<string, mixed>>  $recentAttempts
     * @return list<array{num: int, title: string, meta: string, color: string, action: string, link: string}>
     */
    protected function buildDailyPlan(string $weakestSubject, array $recentAttempts): array
    {
        $lastExamId = $recentAttempts[0]['exam_id'] ?? null;

        return [
            [
                'num' => 1,
                'title' => 'آزمون شبیه‌سازی',
                'meta' => 'یک آزمون کامل برای سنجش آمادگی',
                'color' => 'blue',
                'action' => 'شروع',
                'link' => '/exams',
            ],
            [
                'num' => 2,
                'title' => "تمرین {$weakestSubject}",
                'meta' => 'تمرکز روی ضعیف‌ترین بخش',
                'color' => 'emerald',
                'action' => 'تمرین',
                'link' => '/exams',
            ],
            [
                'num' => 3,
                'title' => 'مطالعه جزوه',
                'meta' => 'مرور مفاهیم کلیدی',
                'color' => 'amber',
                'action' => 'مشاهده',
                'link' => $lastExamId ? "/exams/{$lastExamId}/result/".($recentAttempts[0]['id'] ?? '') : '/my-purchases',
            ],
        ];
    }

    /**
     * @return array{current: int, target_badge: string, days_to_badge: int, target_rank: int, week_days: list<array{label: string, active: bool}>}
     */
    protected function buildStreak(int $userId, int $rank): array
    {
        $activeDays = ExamAttempt::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->whereNotNull('finished_at')
            ->where('finished_at', '>=', now()->subDays(30))
            ->get(['finished_at'])
            ->map(fn (ExamAttempt $a) => $a->finished_at?->toDateString())
            ->filter()
            ->unique()
            ->flip();

        $streak = 0;
        for ($i = 0; $i < 30; $i++) {
            $day = now()->subDays($i)->toDateString();
            if (isset($activeDays[$day])) {
                $streak++;
            } else {
                break;
            }
        }

        $weekLabels = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
        $weekDays = [];
        $start = now()->startOfWeek();
        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $weekDays[] = [
                'label' => $weekLabels[$i],
                'active' => isset($activeDays[$day->toDateString()]),
            ];
        }

        $targetBadge = $streak >= 7 ? 'استاد پیوستگی' : 'پرتکرار';
        $daysToBadge = max(0, 7 - $streak);

        return [
            'current' => $streak,
            'target_badge' => $targetBadge,
            'days_to_badge' => $daysToBadge,
            'target_rank' => max(1, $rank - 3),
            'week_days' => $weekDays,
        ];
    }
}
