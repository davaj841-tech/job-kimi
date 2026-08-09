<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
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
            'question_text' => ['required', 'string'],
            'question_type' => ['required', 'in:multiple_choice,formula'],
            'option_a' => ['nullable', 'string'],
            'option_b' => ['nullable', 'string'],
            'option_c' => ['nullable', 'string'],
            'option_d' => ['nullable', 'string'],
            'correct_answer' => ['required', 'in:a,b,c,d'],
            'explanation' => ['nullable', 'string'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'subject' => ['nullable', 'in:math,literature,islamic,english,chemistry,physics,iq,general'],
        ];
    }
}
