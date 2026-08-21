<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\ExamSubject;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExamSubjectAdminController extends BaseController
{
    public function index(): JsonResponse
    {
        $items = ExamSubject::query()
            ->orderByDesc('is_unmatched')
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
            'is_unmatched' => false,
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
            'name' => ['sometimes', 'string', 'max:100'],
            'slug' => ['sometimes', 'string', 'max:80', Rule::unique('exam_subjects', 'slug')->ignore($id), 'regex:/^[a-z0-9_-]+$/'],
            'icon' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'merge_into_id' => ['nullable', 'integer', 'exists:exam_subjects,id', Rule::notIn([$id])],
            'is_unmatched' => ['sometimes', 'boolean'],
        ], [
            'merge_into_id.exists' => 'درس مقصد یافت نشد.',
        ]);

        // ادغام صریح به درس موجود
        if (! empty($data['merge_into_id'])) {
            $target = ExamSubject::query()->find((int) $data['merge_into_id']);
            if (! $target) {
                return $this->errorResponse('درس مقصد یافت نشد.', 404);
            }

            $sourceName = $item->name;

            return $this->successResponse(
                $this->mergeSubject($item, $target),
                "درس «{$sourceName}» به «{$target->name}» ادغام و سوالات به‌روز شد."
            );
        }

        // اگر نام جدید دقیقاً نام درس دیگری است → ادغام خودکار
        if (isset($data['name'])) {
            $sameName = ExamSubject::query()
                ->where('id', '!=', $id)
                ->where('name', $data['name'])
                ->first();
            if ($sameName) {
                $sourceName = $item->name;

                return $this->successResponse(
                    $this->mergeSubject($item, $sameName),
                    "درس «{$sourceName}» به «{$sameName->name}» ادغام و سوالات به‌روز شد."
                );
            }
        }

        $oldSlug = $item->slug;
        $payload = collect($data)->except(['merge_into_id'])->all();

        // با ویرایش دستی، دیگر نامرتبط نیست
        if (array_key_exists('name', $payload) || array_key_exists('slug', $payload)) {
            $payload['is_unmatched'] = false;
        }

        DB::transaction(function () use ($item, $payload, $oldSlug) {
            $item->update($payload);
            $newSlug = $item->fresh()->slug;
            if ($newSlug !== $oldSlug) {
                Question::query()
                    ->where('subject', $oldSlug)
                    ->update(['subject' => $newSlug]);
            }
        });

        return $this->successResponse($item->fresh(), 'درس به‌روزرسانی شد و سوالات مرتبط اصلاح شد.');
    }

    public function destroy(int $id): JsonResponse
    {
        $item = ExamSubject::query()->find($id);
        if (! $item) {
            return $this->errorResponse('درس یافت نشد.', 404);
        }

        if ($item->questions()->exists()) {
            return $this->errorResponse('این درس در سوالات استفاده شده و قابل حذف نیست. ابتدا به درس دیگری ادغام کنید.', 422);
        }

        $item->delete();

        return $this->successResponse(null, 'درس حذف شد.');
    }

    protected function mergeSubject(ExamSubject $source, ExamSubject $target): ExamSubject
    {
        DB::transaction(function () use ($source, $target) {
            Question::query()
                ->where('subject', $source->slug)
                ->update(['subject' => $target->slug]);

            $source->delete();
        });

        return $target->fresh();
    }
}
