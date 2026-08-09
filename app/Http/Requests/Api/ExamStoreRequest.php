<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('seo_tag')) {
            $tag = preg_replace('/\s+/u', '_', trim((string) $this->input('seo_tag')));
            $tag = preg_replace('/_+/u', '_', $tag ?? '');
            $this->merge(['seo_tag' => trim($tag, '_')]);
        }
    }

    /** @return array<string, mixed> */

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'seo_tag' => [
                'required',
                'string',
                'max:191',
                'regex:/^[\p{L}\p{N}_-]+$/u',
                Rule::unique('exams', 'seo_tag'),
            ],
            'slug' => ['nullable', 'unique:exams,slug', 'regex:/^[a-z0-9-]+$/'],
            'category_id' => ['nullable', 'exists:exam_categories,id'],
            'job_post_id' => ['nullable', 'exists:job_posts,id'],
            'job_classification_id' => [
                'required',
                'integer',
                Rule::exists('job_classifications', 'id')->where(fn ($q) => $q->whereNull('parent_id')),
            ],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:300'],
            'passing_score' => ['required', 'numeric', 'min:0'],
            'total_marks' => ['required', 'numeric', 'min:1'],
            'has_negative_marking' => ['sometimes', 'boolean'],
            'negative_mark_ratio' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'is_free' => ['required', 'boolean'],
            'price' => ['required_if:is_free,false', 'numeric', 'min:0'],
            'subscription_required' => ['required', 'in:free,paid,any'],
            'status' => ['sometimes', 'in:draft,published,archived'],
        ];
    }

    /** @return array<string, string> */

    public function messages(): array
    {
        return [
            'seo_tag.required' => 'برچسب سئو الزامی است.',
            'seo_tag.regex' => 'برچسب سئو فقط حروف، عدد و _ مجاز است.',
            'seo_tag.unique' => 'این برچسب سئو قبلاً استفاده شده است.',
            'job_classification_id.required' => 'انتخاب طبقه‌بندی الزامی است.',
            'job_classification_id.exists' => 'فقط طبقه‌بندی مادر معتبر است.',
        ];
    }
}
