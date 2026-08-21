<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class JobPostStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'title' => ['required', 'string', 'max:255'],
            'seo_tag' => [
                'nullable',
                'string',
                'max:191',
                'regex:/^[\p{L}\p{N}_\-]+$/u',
                Rule::unique('job_posts', 'seo_tag')->ignore($id),
            ],
            'job_classification_id' => ['required', 'integer', 'exists:job_classifications,id'],
            'description' => ['required', 'string', 'max:100000'],
            'provinces' => ['nullable', 'array'],
            'provinces.*' => ['string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'registration_deadline' => array_values(array_filter([
                'required',
                'date',
                $this->isMethod('post') ? 'after_or_equal:today' : null,
            ])),
            'exam_date' => ['nullable', 'date', 'after_or_equal:registration_deadline'],
            'registration_link' => ['nullable', 'url', 'max:500'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,zip,rar,jpg,jpeg,png', 'max:20480'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,zip,rar,jpg,jpeg,png', 'max:20480'],
            'attachment_titles' => ['nullable', 'array'],
            'attachment_titles.*' => ['nullable', 'string', 'max:255'],
            'attachment_descriptions' => ['nullable', 'array'],
            'attachment_descriptions.*' => ['nullable', 'string', 'max:500'],
            'remove_attachment_ids' => ['nullable', 'array'],
            'remove_attachment_ids.*' => ['integer'],
            'is_featured' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:pending,approved,rejected,draft,expired'],
            'auto_catalog' => ['sometimes', 'boolean'],
            'exam_ids' => ['nullable', 'array'],
            'exam_ids.*' => ['integer', 'exists:exams,id'],
            'pdf_ids' => ['nullable', 'array'],
            'pdf_ids.*' => ['integer', 'exists:pdf_products,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'عنوان آگهی الزامی است.',
            'seo_tag.regex' => 'برچسب سئو فقط می‌تواند شامل حروف، عدد، خط زیر و خط تیره باشد (مثال: استخدام_بانک_ملت_1405).',
            'seo_tag.unique' => 'این برچسب سئو قبلاً استفاده شده است.',
            'job_classification_id.required' => 'انتخاب طبقه‌بندی آگهی الزامی است.',
            'job_classification_id.exists' => 'طبقه‌بندی انتخاب‌شده معتبر نیست.',
            'description.required' => 'شرح آگهی الزامی است.',
            'registration_deadline.required' => 'مهلت ثبت‌نام الزامی است.',
            'registration_deadline.after_or_equal' => 'مهلت ثبت‌نام نمی‌تواند قبل از امروز باشد.',
            'exam_date.after_or_equal' => 'تاریخ آزمون باید بعد از مهلت ثبت‌نام باشد.',
            'attachments.max' => 'حداکثر ۱۰ فایل می‌توانید آپلود کنید.',
            'attachments.*.mimes' => 'فرمت یکی از فایل‌ها مجاز نیست.',
            'attachments.*.max' => 'حجم هر فایل نباید بیشتر از ۲۰ مگابایت باشد.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $provinces = $this->input('provinces');

        if (is_string($provinces)) {
            $decoded = json_decode($provinces, true);
            $provinces = json_last_error() === JSON_ERROR_NONE
                ? $decoded
                : array_filter(array_map('trim', explode(',', $provinces)));
        }

        if (! is_array($provinces)) {
            $provinces = [];
        }

        $removeIds = $this->input('remove_attachment_ids');
        if (is_string($removeIds)) {
            $decoded = json_decode($removeIds, true);
            $removeIds = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        $merge = [
            'provinces' => array_values(array_unique(array_filter($provinces))),
            'is_featured' => filter_var($this->input('is_featured', false), FILTER_VALIDATE_BOOLEAN),
            'remove_attachment_ids' => is_array($removeIds) ? $removeIds : [],
            'auto_catalog' => filter_var($this->input('auto_catalog', true), FILTER_VALIDATE_BOOLEAN),
            'exam_ids' => $this->decodeIdList($this->input('exam_ids')),
            'pdf_ids' => $this->decodeIdList($this->input('pdf_ids')),
        ];

        foreach (['city', 'exam_date', 'registration_link', 'source_url', 'seo_tag'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $merge[$key] = null;
            }
        }

        $this->merge($merge);
    }

    /**
     * @param  mixed  $value
     * @return array<int, int>
     */
    protected function decodeIdList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $value))));
    }

    public function withValidator(Validator $validator): void
    {
        //
    }
}
