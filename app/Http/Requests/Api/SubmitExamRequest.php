<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SubmitExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'in:a,b,c,d'],
        ];
    }
}
