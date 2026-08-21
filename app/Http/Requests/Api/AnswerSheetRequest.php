<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AnswerSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Route params only; keep FormRequest for consistency on new endpoints
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
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
