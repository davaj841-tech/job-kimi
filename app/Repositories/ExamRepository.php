<?php

namespace App\Repositories;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ExamRepository
{
    public function getPublished(array $filters): LengthAwarePaginator
    {
        $query = Exam::query()
            ->with(['category', 'classification:id,name,icon,color,logo_path'])
            ->withCount(['questions', 'attempts'])
            ->where('status', $filters['status'] ?? 'published');

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['job_classification_id'])) {
            $classId = (int) $filters['job_classification_id'];
            $childIds = \App\Models\JobClassification::query()->where('parent_id', $classId)->pluck('id')->all();
            $ids = array_merge([$classId], $childIds);
            $query->whereIn('job_classification_id', $ids);
        }

        if (isset($filters['is_free']) && $filters['is_free'] !== '') {
            $query->where('is_free', filter_var($filters['is_free'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['access'])) {
            match ($filters['access']) {
                'free' => $query->where('is_free', true),
                'paid' => $query->where('is_free', false)->where('price', '>', 0),
                'subscription' => $query->where('subscription_required', 'paid'),
                default => null,
            };
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('seo_tag', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['user_id'])) {
            $userId = $filters['user_id'];
            $query->withCount([
                'attempts as user_attempt_count' => fn ($q) => $q->where('user_id', $userId),
            ]);
        }

        $sort = $filters['sort'] ?? 'latest';
        match ($sort) {
            'popular', 'participants' => $query->orderByDesc('attempts_count'),
            'rating' => $query->orderByDesc('avg_rating'),
            default => $query->latest(),
        };

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function findBySlug(string $slug): ?Exam
    {
        return Exam::query()
            ->with(['category', 'classification:id,name,icon,color'])
            ->withCount('questions')
            ->where('slug', $slug)
            ->first();
    }

    public function findById(int $id): ?Exam
    {
        return Exam::query()->with('category')->withCount('questions')->find($id);
    }

    public function getUserAttempts(User $user, int $limit = 5, bool $completedOnly = true): Collection
    {
        return ExamAttempt::query()
            ->with('exam')
            ->where('user_id', $user->id)
            ->when($completedOnly, fn ($q) => $q->where('status', 'completed'))
            ->latest('finished_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function userBestScore(User $user, Exam $exam): ?float
    {
        $best = ExamAttempt::query()
            ->where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'completed')
            ->max('score');

        return $best !== null ? (float) $best : null;
    }

    public function countInProgress(User $user, Exam $exam): int
    {
        return ExamAttempt::query()
            ->where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'in_progress')
            ->count();
    }
}
