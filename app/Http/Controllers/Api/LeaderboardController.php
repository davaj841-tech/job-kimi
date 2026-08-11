<?php

namespace App\Http\Controllers\Api;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $period = $request->query('period', 'all'); // all|week|subject
        $subject = $request->query('subject');

        $query = ExamAttempt::query()
            ->select('user_id', DB::raw('SUM(score) as total_score'), DB::raw('COUNT(*) as attempts'))
            ->where('status', 'completed')
            ->groupBy('user_id')
            ->orderByDesc('total_score')
            ->limit(10);

        if ($period === 'week') {
            $query->where('finished_at', '>=', now()->startOfWeek());
        }

        if ($period === 'subject' && $subject) {
            $examIds = Exam::query()
                ->whereHas('category', fn ($q) => $q->where('name', 'like', "%{$subject}%"))
                ->orWhereHas('questions', fn ($q) => $q->where('subject', $subject))
                ->pluck('id');
            $query->whereIn('exam_id', $examIds);
        }

        $rows = $query->get();
        $users = User::query()->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        $data = $rows->values()->map(function ($row, $i) use ($users) {
            $user = $users->get($row->user_id);

            return [
                'rank' => $i + 1,
                'user_id' => $row->user_id,
                'name' => $user?->name ?: 'کاربر #'.$row->user_id,
                'total_score' => (float) $row->total_score,
                'attempts' => (int) $row->attempts,
            ];
        });

        return $this->successResponse($data);
    }
}
