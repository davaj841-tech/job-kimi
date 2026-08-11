<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // فرمت استاندارد موبایل ایران
            'mobile' => ['required', 'string', 'regex:/^09\d{9}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex' => 'شماره موبایل باید با ۰۹ شروع شود و ۱۱ رقم باشد.',
        ];
    }
}
