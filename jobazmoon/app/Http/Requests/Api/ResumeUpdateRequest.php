<?php

namespace App\Http\Requests\Api;

use App\Rules\ResumeDataRule;
use Illuminate\Foundation\Http\FormRequest;

class ResumeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'template_id' => ['sometimes', 'integer', 'in:1,2,3'],
            'data' => ['sometimes', 'array', new ResumeDataRule],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
