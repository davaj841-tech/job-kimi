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
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'in:draft,published'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->isMethod('post') && ! $this->has('status')) {
            $this->merge(['status' => 'draft']);
        }
    }
}
