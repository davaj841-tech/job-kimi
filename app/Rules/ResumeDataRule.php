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

        $value = $this->normalizeDraft($value);

        $validator = Validator::make($value, [
            'personal' => ['required', 'array'],
            'personal.full_name' => ['nullable', 'string', 'max:100'],
            'personal.birth_date' => ['nullable', 'string', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
            'personal.national_code' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'personal.mobile' => ['nullable', 'string', 'max:11'],
            'personal.email' => ['nullable', 'email'],
            'personal.address' => ['nullable', 'string', 'max:255'],
            'personal.postal_code' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'personal.photo' => ['nullable', 'string', 'max:400000'],
            'personal.birth_province' => ['nullable', 'string', 'max:80'],
            'personal.birth_city' => ['nullable', 'string', 'max:80'],
            'personal.marital_status' => ['nullable', 'string', 'max:30'],
            'personal.field_of_study' => ['nullable', 'string', 'max:120'],
            'personal.home_phone' => ['nullable', 'string', 'max:11'],
            'personal.military_status' => ['nullable', 'string', 'max:40'],
            'personal.insurance_history' => ['nullable', 'string', 'max:80'],
            'education' => ['nullable', 'array'],
            'education.*.degree' => ['nullable', 'in:دیپلم,کاردانی,کارشناسی,ارشد,دکترا'],
            'education.*.field' => ['nullable', 'string', 'max:100'],
            'education.*.university' => ['nullable', 'string', 'max:100'],
            'education.*.start_year' => ['nullable', 'integer', 'min:1300', 'max:1500'],
            'education.*.end_year' => ['nullable', 'integer', 'min:1300', 'max:1500'],
            'education.*.start_date' => ['nullable', 'string', 'max:10'],
            'education.*.end_date' => ['nullable', 'string', 'max:10'],
            'education.*.gpa' => ['nullable', 'string', 'regex:/^\d{1,2}\.\d$/'],
            'experience' => ['nullable', 'array'],
            'experience.*.title' => ['nullable', 'string', 'max:100'],
            'experience.*.company' => ['nullable', 'string', 'max:100'],
            'experience.*.start_date' => ['nullable', 'string', 'max:10'],
            'experience.*.end_date' => ['nullable', 'string', 'max:10'],
            'experience.*.description' => ['nullable', 'string', 'max:2000'],
            'experience.*.is_current' => ['nullable', 'boolean'],
            'skills' => ['nullable', 'array'],
            'skills.*.name' => ['nullable', 'string', 'max:50'],
            'skills.*.level' => ['nullable', 'in:مبتدی,متوسط,حرفه‌ای'],
            'languages' => ['nullable', 'array'],
            'languages.*.name' => ['nullable', 'string', 'max:50'],
            'languages.*.level' => ['nullable', 'in:مبتدی,متوسط,حرفه‌ای,A1,A2,B1,B2,C1,C2'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'target_job' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            $fail($validator->errors()->first());

            return;
        }

        foreach ($value['education'] ?? [] as $edu) {
            if (! isset($edu['gpa']) || $edu['gpa'] === null || $edu['gpa'] === '') {
                continue;
            }
            if ((float) $edu['gpa'] > 20) {
                $fail('معدل حداکثر ۲۰.۰ است.');

                return;
            }
        }
    }

    /**
     * Empty draft fields must be null so regex/email rules do not fire on create.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeDraft(array $data): array
    {
        if (! isset($data['personal']) || ! is_array($data['personal'])) {
            return $data;
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

        $postal = preg_replace('/\D/', '', (string) ($data['personal']['postal_code'] ?? '')) ?? '';
        $data['personal']['postal_code'] = preg_match('/^\d{10}$/', $postal) ? $postal : null;

        $national = preg_replace('/\D/', '', (string) ($data['personal']['national_code'] ?? '')) ?? '';
        $data['personal']['national_code'] = preg_match('/^\d{10}$/', $national) ? $national : null;

        return $data;
    }
}
