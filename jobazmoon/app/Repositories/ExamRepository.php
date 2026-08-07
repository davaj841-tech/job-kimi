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
            ->with('category')
            ->withCount('questions')
            ->where('status', $filters['status'] ?? 'published');

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['is_free']) && $filters['is_free'] !== '') {
            $query->where('is_free', filter_var($filters['is_free'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['price_min']) && $filters['price_min'] !== '') {
            $query->where('price', '>=', (int) $filters['price_min']);
        }
        if (isset($filters['price_max']) && $filters['price_max'] !== '') {
            $query->where('price', '<=', (int) $filters['price_max']);
        }
        if (isset($filters['duration_min']) && $filters['duration_min'] !== '') {
            $query->where('duration_minutes', '>=', (int) $filters['duration_min']);
        }
        if (isset($filters['duration_max']) && $filters['duration_max'] !== '') {
            $query->where('duration_minutes', '<=', (int) $filters['duration_max']);
        }
        if (isset($filters['questions_min']) && $filters['questions_min'] !== '') {
            $query->where('total_questions', '>=', (int) $filters['questions_min']);
        }
        if (isset($filters['questions_max']) && $filters['questions_max'] !== '') {
            $query->where('total_questions', '<=', (int) $filters['questions_max']);
        }
        if (! empty($filters['subjects']) && is_array($filters['subjects'])) {
            $subjects = $filters['subjects'];
            $query->whereHas('questions', fn ($q) => $q->whereIn('subject', $subjects));
        }

        if (! empty($filters['user_id'])) {
            $userId = $filters['user_id'];
            $query->withCount([
                'attempts as user_attempt_count' => fn ($q) => $q->where('user_id', $userId),
            ]);
        }

        $sort = $filters['sort'] ?? 'latest';
        match ($sort) {
            'popular' => $query->orderByDesc('attempts_count'),
            'participants' => $query->orderByDesc('attempts_count'),
            'rating' => $query->orderByDesc('avg_rating'),
            default => $query->latest(),
        };

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function findBySlug(string $slug): ?Exam
    {
        return Exam::query()
            ->with('category')
            ->withCount('questions')
            ->where('slug', $slug)
            ->first();
    }

    public function findById(int $id): ?Exam
    {
        return Exam::query()->with('category')->withCount('questions')->find($id);
    }

    public function getUserAttempts(User $user, int $limit = 5): Collection
    {
        return ExamAttempt::query()
            ->with('exam')
            ->where('user_id', $user->id)
            ->latest()
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
