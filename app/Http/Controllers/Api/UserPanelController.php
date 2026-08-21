<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
use App\Models\ExamAttempt;
use App\Models\PageView;
use App\Repositories\ExamRepository;
use App\Services\Auth\LoginSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserPanelController extends BaseController
{
    public function __construct(
        protected ExamRepository $examRepository
    ) {}

    /**
     * Thin alias of dashboard stats for the user panel.
     */
    public function dashboardStats(Request $request): JsonResponse
    {
        return app(DashboardController::class)->index($request);
    }

    public function recentExams(Request $request): JsonResponse
    {
        $limit = min(20, max(1, (int) $request->query('limit', 5)));
        $user = $request->user();
        $items = $this->mapAttempts(
            $this->examRepository->getUserAttempts($user, $limit)
        );

        return $this->successResponse(['items' => $items]);
    }

    public function skillsAnalysis(Request $request): JsonResponse
    {
        /** @var DashboardController $dashboard */
        $dashboard = app(DashboardController::class);
        $chart = $dashboard->buildProgressChart($request->user()->id);

        return $this->successResponse(['skills' => $chart]);
    }

    public function examHistory(Request $request): JsonResponse
    {
        $limit = min(200, max(1, (int) $request->query('limit', 50)));
        $user = $request->user();
        $items = $this->mapAttempts(
            $this->examRepository->getUserAttempts($user, $limit)
        );

        return $this->successResponse(['items' => $items]);
    }

    public function activity(Request $request): JsonResponse
    {
        $report = app(LoginSessionService::class)->reportForUser($request->user(), 60);

        $pageViews = PageView::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(20)
            ->get(['page_url', 'route_name', 'created_at'])
            ->map(fn (PageView $row) => [
                'page_url' => $row->page_url,
                'route_name' => $row->route_name,
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->all();

        return $this->successResponse([
            'sessions' => $report['sessions'],
            'monthly' => $report['monthly'],
            'items' => $pageViews,
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ], [
            'current.required' => 'رمز فعلی الزامی است.',
            'password.confirmed' => 'تکرار رمز عبور مطابقت ندارد.',
        ]);

        if (blank($user->password) || ! Hash::check($data['current'], $user->password)) {
            return $this->errorResponse('رمز فعلی نادرست است.', 422);
        }

        $user->update(['password' => $data['password']]);

        $currentId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();

        return $this->successResponse(new UserResource($user->fresh()->load('subscriptionPlan')), 'رمز عبور به‌روزرسانی شد.');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, ExamAttempt>|\Illuminate\Support\Collection<int, ExamAttempt>|iterable<ExamAttempt>  $attempts
     * @return list<array<string, mixed>>
     */
    protected function mapAttempts(iterable $attempts): array
    {
        return collect($attempts)->map(fn (ExamAttempt $attempt) => $attempt->toHistoryItem())->values()->all();
    }
}
