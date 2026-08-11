<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\Aggregation\JobCrawlerType;
use App\Enums\Aggregation\JobSourceQualityStatus;
use App\Enums\Aggregation\JobSourceReliability;
use App\Enums\Aggregation\JobSourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobSourceStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:190'],
            'slug' => [
                'nullable',
                'string',
                'max:190',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('job_sources', 'slug')->ignore($id),
            ],
            'official_url' => ['required', 'url', 'max:500'],
            'domain' => ['nullable', 'string', 'max:190'],
            'source_type' => ['required', Rule::enum(JobSourceType::class)],
            'reliability_level' => ['required', Rule::enum(JobSourceReliability::class)],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_approved' => ['sometimes', 'boolean'],
            'quality_status' => ['nullable', Rule::enum(JobSourceQualityStatus::class)],
            'quality_notes' => ['nullable', 'string', 'max:2000'],
            'crawler_type' => ['required', Rule::enum(JobCrawlerType::class)],
            'crawl_frequency' => ['nullable', 'string', 'max:40'],
            'schedule_mode' => ['nullable', 'string', Rule::in(['global', 'custom'])],
            'custom_schedule_times' => ['nullable', 'array', 'max:24'],
            'custom_schedule_times.*.time' => ['required_with:custom_schedule_times', 'string', 'max:5'],
            'custom_schedule_times.*.enabled' => ['sometimes', 'boolean'],
            'custom_schedule_times.*.label' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'نام منبع الزامی است.',
            'official_url.required' => 'آدرس رسمی الزامی است.',
            'official_url.url' => 'آدرس رسمی معتبر نیست.',
            'source_type.required' => 'نوع منبع الزامی است.',
            'reliability_level.required' => 'سطح اعتماد الزامی است.',
            'crawler_type.required' => 'نوع خزنده الزامی است.',
            'slug.unique' => 'این شناسه قبلاً استفاده شده است.',
            'slug.regex' => 'شناسه فقط می‌تواند شامل حروف کوچک انگلیسی، عدد و خط تیره باشد.',
        ];
    }
}
