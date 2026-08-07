<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class QuestionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exam_id' => ['sometimes', 'exists:exams,id'],
            'question_text' => ['sometimes', 'string', 'max:5000'],
            'question_type' => ['sometimes', 'in:multiple_choice,formula'],
            'option_a' => ['sometimes', 'string', 'max:2000'],
            'option_b' => ['sometimes', 'string', 'max:2000'],
            'option_c' => ['sometimes', 'string', 'max:2000'],
            'option_d' => ['sometimes', 'string', 'max:2000'],
            'correct_answer' => ['sometimes', 'in:a,b,c,d'],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'difficulty' => ['sometimes', 'in:easy,medium,hard'],
            'subject' => ['sometimes', 'in:math,literature,islamic,english,chemistry,physics,iq,general'],
        ];
    }
}
