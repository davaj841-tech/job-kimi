<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AnswerSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Route params only; keep FormRequest for consistency on new endpoints
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $examId = (int) $this->route('id');
            $attemptId = (int) $this->route('attemptId');

            if ($examId < 1) {
                $validator->errors()->add('id', 'شناسه آزمون نامعتبر است.');
            }

            if ($attemptId < 1) {
                $validator->errors()->add('attemptId', 'شناسه تلاش نامعتبر است.');
            }
        });
    }
}
