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
            'template_id' => ['nullable', 'integer', 'between:1,10'],
            'data' => ['required', 'array', new ResumeDataRule],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->input('data');
        if (! is_array($data) || ! isset($data['personal']) || ! is_array($data['personal'])) {
            return;
        }
        foreach ($data['personal'] as $key => $val) {
            if ($val === '') {
                $data['personal'][$key] = null;
            }
        }
        $birth = $data['personal']['birth_date'] ?? null;
        if (is_string($birth) && ! preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $birth)) {
            $data['personal']['birth_date'] = null;
        }
        $this->merge(['data' => $data]);
    }
}
