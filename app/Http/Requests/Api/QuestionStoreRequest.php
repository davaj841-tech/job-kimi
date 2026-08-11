<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class QuestionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'exam_id' => ['required', 'exists:exams,id'],
            'question_text' => ['required', 'string', 'max:5000'],
            'question_type' => ['required', 'in:multiple_choice,formula'],
            'option_a' => ['required', 'string', 'max:2000'],
            'option_b' => ['required', 'string', 'max:2000'],
            'option_c' => ['required', 'string', 'max:2000'],
            'option_d' => ['required', 'string', 'max:2000'],
            'correct_answer' => ['required', 'in:a,b,c,d'],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'subject' => ['required', 'string', 'exists:exam_subjects,slug'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $difficulty = $this->input('difficulty');
        if ($difficulty === null || $difficulty === '') {
            $this->merge(['difficulty' => 'medium']);
        }
    }
}
