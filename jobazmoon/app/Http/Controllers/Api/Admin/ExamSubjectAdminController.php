<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\ExamSubject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExamSubjectAdminController extends BaseController
{
    public function index(): JsonResponse
    {
        $items = ExamSubject::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->successResponse($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:exam_subjects,name'],
            'slug' => ['nullable', 'string', 'max:80', 'unique:exam_subjects,slug', 'regex:/^[a-z0-9_-]+$/'],
            'icon' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'نام درس الزامی است.',
            'name.unique' => 'این درس قبلاً ثبت شده است.',
        ]);

        $slug = $data['slug'] ?? Str::slug($data['name']);
        if ($slug === '') {
            $slug = 'subject-'.Str::random(6);
        }

        $item = ExamSubject::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'icon' => $data['icon'] ?? '📘',
            'sort_order' => $data['sort_order'] ?? ((int) ExamSubject::query()->max('sort_order') + 1),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $this->successResponse($item, 'درس ایجاد شد.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = ExamSubject::query()->find($id);
        if (! $item) {
            return $this->errorResponse('درس یافت نشد.', 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('exam_subjects', 'name')->ignore($id)],
            'slug' => ['sometimes', 'string', 'max:80', Rule::unique('exam_subjects', 'slug')->ignore($id), 'regex:/^[a-z0-9_-]+$/'],
            'icon' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $item->update($data);

        return $this->successResponse($item->fresh(), 'درس به‌روزرسانی شد.');
    }

    public function destroy(int $id): JsonResponse
    {
        $item = ExamSubject::query()->find($id);
        if (! $item) {
            return $this->errorResponse('درس یافت نشد.', 404);
        }

        if ($item->questions()->exists()) {
            return $this->errorResponse('این درس در سوالات استفاده شده و قابل حذف نیست.', 422);
        }

        $item->delete();

        return $this->successResponse(null, 'درس حذف شد.');
    }
}
