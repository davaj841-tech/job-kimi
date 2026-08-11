<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'mobile' => ['required', 'regex:/^09\d{9}$/', Rule::unique('users', 'mobile')->ignore($id)],
            'national_code' => ['nullable', 'string', 'max:10'],
            'username' => ['nullable', 'regex:/^[a-z0-9_]{3,20}$/', Rule::unique('users', 'username')->ignore($id)],
            'role' => ['sometimes', 'in:jobseeker,employer,operator,admin'],
            'status' => ['sometimes', 'in:active,blocked'],
            'password' => ['nullable', 'string', 'min:8', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'],
            'is_verified' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex' => 'شماره موبایل معتبر نیست.',
            'mobile.unique' => 'این موبایل قبلاً ثبت شده است.',
            'email.email' => 'ایمیل معتبر نیست.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
            'username.regex' => 'نام کاربری باید ۳ تا ۲۰ کاراکتر و فقط حروف کوچک، عدد و ـ باشد.',
            'username.unique' => 'این نام کاربری قبلاً ثبت شده است.',
            'role.in' => 'نقش انتخاب‌شده معتبر نیست.',
            'status.in' => 'وضعیت انتخاب‌شده معتبر نیست.',
            'password.min' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
            'password.regex' => 'رمز عبور باید شامل حرف و عدد باشد.',
        ];
    }
}
