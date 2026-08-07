<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'job_category' => ['nullable', 'string', 'max:100'],
            'registration_deadline' => ['nullable', 'date'],
            'exam_date' => ['nullable', 'date'],
            'registration_link' => ['nullable', 'url'],
            'source_url' => ['nullable', 'url'],
            'is_featured' => ['boolean'],
        ];
    }
}
