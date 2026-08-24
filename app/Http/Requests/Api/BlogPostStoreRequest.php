<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogPostStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $blogPostId = $this->route('id') ?? $this->route('blog_post');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('blog_posts', 'slug')->ignore($blogPostId),
            ],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'featured_image' => $this->hasFile('featured_image')
                ? ['nullable', 'image', 'max:2048']
                : ['nullable', 'string', 'max:500'],
            'category' => ['required', 'string', 'max:100'],
            'job_classification_id' => ['nullable', 'integer', 'exists:job_classifications,id'],
            'auto_catalog' => ['sometimes', 'boolean'],
            'exam_ids' => ['nullable', 'array'],
            'exam_ids.*' => ['integer', 'exists:exams,id'],
            'pdf_ids' => ['nullable', 'array'],
            'pdf_ids.*' => ['integer', 'exists:pdf_products,id'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'in:draft,published'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'auto_catalog' => filter_var($this->input('auto_catalog', true), FILTER_VALIDATE_BOOLEAN),
            'exam_ids' => $this->decodeIdList($this->input('exam_ids')),
            'pdf_ids' => $this->decodeIdList($this->input('pdf_ids')),
        ];
        if ($this->isMethod('post') && ! $this->has('status')) {
            $merge['status'] = 'draft';
        }
        if ($this->has('job_classification_id') && $this->input('job_classification_id') === '') {
            $merge['job_classification_id'] = null;
        }
        $this->merge($merge);
    }

    /**
     * @return array<int, int>
     */
    protected function decodeIdList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $value))));
    }
}
