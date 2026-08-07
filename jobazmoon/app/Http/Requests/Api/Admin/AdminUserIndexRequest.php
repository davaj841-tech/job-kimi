<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['jobseeker', 'employer', 'operator', 'admin'])],
            'status' => ['nullable', Rule::in(['active', 'blocked'])],
            'sort' => ['nullable', Rule::in(['desc', 'asc', 'wallet_desc', 'newest', 'oldest', 'wallet'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
