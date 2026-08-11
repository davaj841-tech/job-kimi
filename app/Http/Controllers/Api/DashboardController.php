<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\DashboardResource;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSubject;
use App\Models\Question;
use App\Repositories\ExamRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            ->with('exam:id,title,total_marks,passing_score')
            ->where('user_id', $user->id)
            ->where('status', 'completed');

        $completedAttempts = (clone $completed)->latest('finished_at')->latest('id')->get();

        $totalTaken = $completedAttempts->count();
        $totalPassed = $completedAttempts->filter(function (ExamAttempt $attempt) {
            return (float) $attempt->score >= (float) ($attempt->exam?->passing_score ?? 0);
        })->count();

        $avgScore = $totalTaken > 0 ? round((float) $completedAttempts->avg('score'), 2) : 0;
        $totalCorrect = (int) $completedAttempts->sum('total_correct');
        $totalWrong = (int) $completedAttempts->sum('total_wrong');

        $examsThisWeek = $completedAttempts
            ->filter(fn (ExamAttempt $a) => $a->finished_at && $a->finished_at->gte(now()->subDays(7)))
            ->count();

        $scoreTrend = '';
        if ($completedAttempts->count() >= 2) {
            $latest = (float) $completedAttempts->first()?->score;
            $previous = (float) $completedAttempts->skip(1)->first()?->score;
            $delta = round($latest - $previous, 1);
            $scoreTrend = ($delta >= 0 ? '+' : '').$delta.' نسبت به قبل';
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

        $recent = $this->examRepository->getUserAttempts($user, 10)->map(function (ExamAttempt $attempt) {
            $totalMarks = (float) ($attempt->exam?->total_marks ?: 1);

            return [
                'id' => $attempt->id,
                'exam_id' => $attempt->exam_id,
                'exam_title' => $attempt->exam?->title,
                'exam_slug' => $attempt->exam?->slug,
                'subject' => $attempt->subject,
                'score' => $attempt->score,
                'total_correct' => $attempt->total_correct,
                'total_wrong' => $attempt->total_wrong,
                'total_marks' => $attempt->exam?->total_marks,
                'percentage' => round(((float) $attempt->score / $totalMarks) * 100, 2),
                'created_at' => $attempt->created_at?->toIso8601String(),
                'finished_at' => $attempt->finished_at?->toIso8601String(),
                'status' => $attempt->status,
            ];
        })->values()->all();

        // ۱۲ تلاش اخیر (از جدید به قدیم خوانده شده؛ برای نمودار از قدیم به جدید)
        $examChart = $completedAttempts->take(12)->reverse()->values()->map(function (ExamAttempt $a) {
            $totalMarks = (float) ($a->exam?->total_marks ?: 1);

            return [
                'label' => Str::limit($a->exam?->title ?: 'آزمون', 18),
                'percentage' => round(((float) $a->score / $totalMarks) * 100, 1),
                'score' => (float) $a->score,
            ];
        })->all();

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
                'average_score' => $avgScore,
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
        ];

        return $this->successResponse(new DashboardResource($payload));
    }

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
            $label = ExamSubject::query()->where('slug', $subject)->value('name') ?: $subject;

            return [
                'subject' => $subject,
                'subject_label' => $label,
                'average_score' => $stat['total'] > 0 ? round(($stat['correct'] / $stat['total']) * 100, 2) : 0,
                'exam_count' => count($stat['exam_ids']),
            ];
        })->values()->all();
    }
}
