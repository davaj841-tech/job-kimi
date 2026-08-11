<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\BlogPost;
use App\Models\CrawlerRun;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Models\PdfPurchase;
use App\Models\Question;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends BaseController
{
    public function stats(): JsonResponse
    {
        $todayStart = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $chartStart = now()->subDays(29)->startOfDay();

        $successToday = Transaction::query()
            ->where('status', 'success')
            ->where('created_at', '>=', $todayStart);

        $successMonth = Transaction::query()
            ->where('status', 'success')
            ->where('created_at', '>=', $monthStart);

        $counts = [
            'users' => User::query()->count(),
            'exams' => Exam::query()->count(),
            'questions' => Question::query()->count(),
            'active_subscriptions' => User::query()
                ->whereNotNull('subscription_expires_at')
                ->where('subscription_expires_at', '>', now())
                ->count(),
            'pdf_sales' => PdfPurchase::query()->count(),
            'today_revenue' => (int) (clone $successToday)->sum('amount'),
            'month_revenue' => (int) (clone $successMonth)->sum('amount'),
            'transactions_today' => (clone $successToday)->count(),
            'aggregated_jobs_pending' => JobPost::query()->whereNotNull('job_source_id')->where('status', 'pending')->count(),
            'aggregated_jobs_total' => JobPost::query()->whereNotNull('job_source_id')->count(),
            'whitelisted_job_sources' => JobSource::query()->whitelisted()->count(),
            'recent_crawl_failures' => CrawlerRun::query()->whereIn('status', ['failed', 'partial'])->where('finished_at', '>=', now()->subDays(7))->count(),
        ];

        $revenueByDay = Transaction::query()
            ->where('status', 'success')
            ->where('created_at', '>=', $chartStart)
            ->get(['amount', 'created_at'])
            ->groupBy(fn (Transaction $t) => $t->created_at->format('Y-m-d'))
            ->map(fn (Collection $rows) => (int) $rows->sum('amount'));

        $usersByDay = User::query()
            ->where('created_at', '>=', $chartStart)
            ->get(['created_at'])
            ->groupBy(fn (User $u) => $u->created_at->format('Y-m-d'))
            ->map(fn (Collection $rows) => $rows->count());

        $examsByDay = ExamAttempt::query()
            ->where('status', 'completed')
            ->where('finished_at', '>=', $chartStart)
            ->get(['finished_at'])
            ->groupBy(fn (ExamAttempt $a) => optional($a->finished_at)->format('Y-m-d') ?: 'unknown')
            ->map(fn (Collection $rows) => $rows->count());

        $charts = [
            'revenue' => $this->fillDailySeries($chartStart, $revenueByDay, 'amount'),
            'users' => $this->fillDailySeries($chartStart, $usersByDay, 'count'),
            'exams' => $this->fillDailySeries($chartStart, $examsByDay, 'count'),
        ];

        $subscriptionDistribution = SubscriptionPlan::query()
            ->where('is_active', true)
            ->get(['id', 'name'])
            ->map(function (SubscriptionPlan $plan) {
                return [
                    'label' => $plan->name,
                    'value' => User::query()
                        ->where('subscription_plan_id', $plan->id)
                        ->whereNotNull('subscription_expires_at')
                        ->where('subscription_expires_at', '>', now())
                        ->count(),
                ];
            })
            ->filter(fn (array $row) => $row['value'] > 0)
            ->values()
            ->all();

        if ($subscriptionDistribution === []) {
            $subscriptionDistribution = [
                ['label' => 'بدون اشتراک فعال', 'value' => max(1, User::query()->count())],
            ];
        }

        $recent = [
            'users' => User::query()
                ->latest()
                ->limit(5)
                ->get(['id', 'name', 'mobile', 'created_at'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name ?: '—',
                    'mobile' => $u->mobile,
                    'created_at' => $u->created_at?->toIso8601String(),
                ])->all(),
            'exams' => ExamAttempt::query()
                ->with(['exam:id,title', 'user:id,name'])
                ->where('status', 'completed')
                ->latest('finished_at')
                ->limit(5)
                ->get()
                ->map(fn (ExamAttempt $a) => [
                    'id' => $a->id,
                    'title' => $a->exam?->title ?: '—',
                    'user_name' => $a->user?->name ?: '—',
                    'score' => $a->score,
                    'created_at' => ($a->finished_at ?? $a->created_at)?->toIso8601String(),
                ])->all(),
            'purchases' => PdfPurchase::query()
                ->with(['user:id,name', 'pdfProduct:id,title'])
                ->latest('purchased_at')
                ->limit(5)
                ->get()
                ->map(fn (PdfPurchase $p) => [
                    'id' => $p->id,
                    'user_name' => $p->user?->name ?: '—',
                    'product_name' => $p->pdfProduct?->title ?: '—',
                    'amount' => $p->price_paid,
                    'created_at' => ($p->purchased_at ?? $p->created_at)?->toIso8601String(),
                ])->all(),
            'job_posts' => JobPost::query()
                ->latest()
                ->limit(5)
                ->get(['id', 'title', 'company_name', 'status', 'created_at'])
                ->map(fn (JobPost $j) => [
                    'id' => $j->id,
                    'title' => $j->title,
                    'company_name' => $j->company_name,
                    'status' => $j->status,
                    'created_at' => $j->created_at?->toIso8601String(),
                ])->all(),
            'blog_posts' => BlogPost::query()
                ->with('creator:id,name')
                ->latest()
                ->limit(5)
                ->get(['id', 'title', 'status', 'created_by', 'created_at'])
                ->map(fn (BlogPost $b) => [
                    'id' => $b->id,
                    'title' => $b->title,
                    'author' => $b->creator?->name ?: '—',
                    'status' => $b->status,
                    'created_at' => $b->created_at?->toIso8601String(),
                ])->all(),
        ];

        $yesterdayRevenue = (int) Transaction::query()
            ->where('status', 'success')
            ->whereDate('created_at', now()->subDay()->toDateString())
            ->sum('amount');

        $counts['yesterday_revenue'] = $yesterdayRevenue;
        $counts['revenue_change_pct'] = $yesterdayRevenue > 0
            ? round((($counts['today_revenue'] - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : ($counts['today_revenue'] > 0 ? 100 : 0);
        $counts['exams_in_progress'] = ExamAttempt::query()->where('status', 'in_progress')->count();
        $counts['users_online'] = User::query()
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->count();

        $topExams = Exam::query()
            ->orderByDesc('attempts_count')
            ->limit(10)
            ->get(['id', 'title', 'attempts_count', 'avg_rating'])
            ->map(fn (Exam $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'attempts' => (int) $e->attempts_count,
                'rating' => (float) $e->avg_rating,
            ])->all();

        $topUsers = ExamAttempt::query()
            ->select('user_id', DB::raw('COUNT(*) as activity'), DB::raw('SUM(score) as total_score'))
            ->where('status', 'completed')
            ->groupBy('user_id')
            ->orderByDesc('activity')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $u = User::query()->find($row->user_id);

                return [
                    'user_id' => $row->user_id,
                    'name' => $u?->name ?: 'کاربر #'.$row->user_id,
                    'activity' => (int) $row->activity,
                    'total_score' => (float) $row->total_score,
                ];
            })->all();

        $hourly = collect(range(0, 23))->map(function ($h) {
            $count = ExamAttempt::query()
                ->whereDate('created_at', today())
                ->get(['created_at'])
                ->filter(fn ($a) => $a->created_at && (int) $a->created_at->format('G') === $h)
                ->count();

            return ['hour' => $h, 'count' => $count];
        })->all();

        $charts['hourly_today'] = $hourly;
        $charts['top_exams'] = $topExams;
        $charts['devices'] = collect(app(AnalyticsService::class)->devices(now()->subDays(29), now()))
            ->map(fn ($row) => [
                'label' => match ($row['device']) {
                    'mobile' => 'موبایل',
                    'tablet' => 'تبلت',
                    default => 'دسکتاپ',
                },
                'value' => $row['count'],
            ])
            ->filter(fn ($r) => $r['value'] > 0)
            ->values()
            ->all();

        if ($charts['devices'] === []) {
            $charts['devices'] = [
                ['label' => 'موبایل', 'value' => 0],
                ['label' => 'دسکتاپ', 'value' => 0],
            ];
        }

        $analytics = app(AnalyticsService::class);
        $counts['visits_today'] = $analytics->todayCount();
        $counts['visits_month'] = $analytics->monthCount();
        $charts['visits'] = $analytics->visits(
            now()->subDays(29)->toDateString(),
            now()->toDateString(),
            'day'
        );
        $topPages = $analytics->topPages(10, now()->subDays(29), now());

        return $this->successResponse([
            'counts' => $counts,
            'charts' => $charts,
            'subscription_distribution' => $subscriptionDistribution,
            'recent' => $recent,
            'top_users' => $topUsers,
            'top_exams' => $topExams,
            'top_pages' => $topPages,
        ]);
    }

    /**
     * @param  Collection<string, int>  $grouped
     * @return array<int, array<string, mixed>>
     */
    protected function fillDailySeries(Carbon $start, Collection $grouped, string $valueKey): array
    {
        $series = [];
        $period = CarbonPeriod::create($start->copy()->startOfDay(), now()->startOfDay());

        foreach ($period as $day) {
            /** @var Carbon $day */
            $key = $day->format('Y-m-d');
            $series[] = [
                'date' => $key,
                $valueKey => (int) ($grouped[$key] ?? 0),
            ];
        }

        return $series;
    }
}
