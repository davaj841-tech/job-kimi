<?php

namespace App\Http\Requests\Api;

use App\Rules\ResumeDataRule;
use Illuminate\Foundation\Http\FormRequest;

class ResumeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'template_id' => ['nullable', 'integer', 'in:1,2,3'],
            'data' => ['required', 'array', new ResumeDataRule],
        ];
    }
}
