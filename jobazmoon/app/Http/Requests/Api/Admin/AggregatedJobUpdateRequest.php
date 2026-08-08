<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edit aggregated pending jobs before publish.
 * Softer than manual JobPostStoreRequest — does not invent missing fields.
 */
class AggregatedJobUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'title' => ['required', 'string', 'max:255'],
            'seo_tag' => [
                'nullable',
                'string',
                'max:191',
                'regex:/^[\p{L}\p{N}_\-]+$/u',
                Rule::unique('job_posts', 'seo_tag')->ignore($id),
            ],
            'job_classification_id' => ['nullable', 'integer', 'exists:job_classifications,id'],
            'description' => ['nullable', 'string', 'max:100000'],
            'requirements' => ['nullable', 'string', 'max:100000'],
            'education' => ['nullable', 'string', 'max:190'],
            'field_of_study' => ['nullable', 'string', 'max:190'],
            'experience' => ['nullable', 'string', 'max:190'],
            'employment_type' => ['nullable', 'string', 'max:80'],
            'provinces' => ['nullable', 'array'],
            'provinces.*' => ['string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'job_category' => ['nullable', 'string', 'max:190'],
            'registration_starts_at' => ['nullable', 'date'],
            'registration_deadline' => ['nullable', 'date'],
            'exam_date' => ['nullable', 'date', 'after_or_equal:registration_deadline'],
            'published_at' => ['nullable', 'date'],
            'registration_link' => ['nullable', 'url', 'max:500'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'is_featured' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:pending,approved,rejected'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('provinces')) {
            $provinces = $this->input('provinces');
            if (is_string($provinces)) {
                $decoded = json_decode($provinces, true);
                $provinces = json_last_error() === JSON_ERROR_NONE
                    ? $decoded
                    : array_filter(array_map('trim', explode(',', $provinces)));
            }
            if (! is_array($provinces)) {
                $provinces = [];
            }
            $merge['provinces'] = array_values(array_unique(array_filter($provinces)));
        }

        foreach ([
            'city', 'exam_date', 'registration_link', 'source_url', 'seo_tag',
            'education', 'field_of_study', 'experience', 'employment_type', 'requirements',
            'registration_starts_at', 'published_at', 'job_category', 'province',
        ] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $merge[$key] = null;
            }
        }
        $this->merge($merge);
    }
}
