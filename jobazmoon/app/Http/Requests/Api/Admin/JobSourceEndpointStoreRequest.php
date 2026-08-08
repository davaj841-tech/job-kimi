<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\Aggregation\JobEndpointType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobSourceEndpointStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:500'],
            'endpoint_type' => ['required', Rule::enum(JobEndpointType::class)],
            'http_method' => ['nullable', 'string', Rule::in(['GET'])],
            'parser_type' => ['nullable', 'string', 'max:80'],
            'is_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'url.required' => 'آدرس endpoint الزامی است.',
            'url.url' => 'آدرس endpoint معتبر نیست.',
            'endpoint_type.required' => 'نوع endpoint الزامی است.',
            'http_method.in' => 'در حال حاضر فقط روش GET پشتیبانی می‌شود.',
        ];
    }
}
