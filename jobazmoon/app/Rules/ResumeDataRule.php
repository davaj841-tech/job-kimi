<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

class ResumeDataRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('ساختار داده رزومه نامعتبر است.');

            return;
        }

        $validator = Validator::make($value, [
            'personal' => ['required', 'array'],
            'personal.full_name' => ['required', 'string', 'max:100'],
            'personal.birth_date' => ['required', 'date'],
            'personal.national_code' => ['required', 'string', 'size:10'],
            'personal.mobile' => ['required', 'string', 'size:11'],
            'personal.email' => ['required', 'email'],
            'personal.address' => ['nullable', 'string', 'max:255'],
            'personal.photo' => ['nullable', 'string', 'max:500'],
            'education' => ['required', 'array', 'min:1'],
            'education.*.degree' => ['required', 'in:دیپلم,کاردانی,کارشناسی,ارشد,دکترا'],
            'education.*.field' => ['required', 'string', 'max:100'],
            'education.*.university' => ['required', 'string', 'max:100'],
            'education.*.start_year' => ['nullable', 'integer', 'min:1300', 'max:1500'],
            'education.*.end_year' => ['nullable', 'integer', 'min:1300', 'max:1500'],
            'education.*.gpa' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'experience' => ['nullable', 'array'],
            'experience.*.title' => ['required', 'string', 'max:100'],
            'experience.*.company' => ['required', 'string', 'max:100'],
            'experience.*.start_date' => ['nullable', 'string', 'max:7'],
            'experience.*.end_date' => ['nullable', 'string', 'max:7'],
            'experience.*.description' => ['nullable', 'string', 'max:2000'],
            'experience.*.is_current' => ['nullable', 'boolean'],
            'skills' => ['required', 'array', 'min:1'],
            'skills.*.name' => ['required', 'string', 'max:50'],
            'skills.*.level' => ['nullable', 'in:مبتدی,متوسط,حرفه‌ای'],
            'languages' => ['nullable', 'array'],
            'languages.*.name' => ['required', 'string', 'max:50'],
            'languages.*.level' => ['nullable', 'in:A1,A2,B1,B2,C1,C2'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'target_job' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            $fail($validator->errors()->first());
        }
    }
}
