<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:exams,slug'],
            'category_id' => ['required', 'exists:exam_categories,id'],
            'job_post_id' => ['nullable', 'exists:job_posts,id'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'passing_score' => ['nullable', 'integer', 'min:0'],
            'total_questions' => ['nullable', 'integer', 'min:0'],
            'total_marks' => ['nullable', 'integer', 'min:0'],
            'is_free' => ['boolean'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'subscription_required' => ['nullable', 'in:free,paid,any'],
            'status' => ['nullable', 'in:draft,published,archived'],
        ];
    }
}
