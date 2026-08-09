<?php

namespace App\Repositories;

use App\Models\Question;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class QuestionRepository
{
    public function getByExam(int $examId): Collection
    {
        return Question::query()->where('exam_id', $examId)->get();
    }

    public function getFiltered(array $filters): LengthAwarePaginator
    {
        $query = Question::query()->with('exam:id,title,slug');

        if (! empty($filters['exam_id'])) {
            $query->where('exam_id', $filters['exam_id']);
        }

        if (! empty($filters['subject'])) {
            $query->where('subject', $filters['subject']);
        }

        if (! empty($filters['difficulty'])) {
            $query->where('difficulty', $filters['difficulty']);
        }

        if (! empty($filters['question_type'])) {
            $query->where('question_type', $filters['question_type']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('question_text', 'like', "%{$search}%");
        }

        return $query->latest()->paginate($filters['per_page'] ?? 20);
    }

    public function find(int $id): ?Question
    {
        return Question::query()->find($id);
    }
}
