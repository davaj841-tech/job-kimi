<?php

namespace App\Http\Requests\Auth;

use App\Support\IranMobile;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mobile = IranMobile::normalize($this->input('mobile'));
        if ($mobile !== null) {
            $this->merge(['mobile' => $mobile]);
        }
        if ($this->has('code')) {
            $this->merge(['code' => preg_replace('/\D+/', '', (string) $this->input('code'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'code' => ['required', 'string', 'digits:5'],
            'province' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex' => 'شماره موبایل نامعتبر است.',
            'code.required' => 'کد تایید الزامی است.',
            'code.digits' => 'کد تایید باید ۵ رقم باشد.',
            'province.max' => 'نام استان معتبر نیست.',
        ];
    }
}
