<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageAdminController extends BaseController
{
    public function index(): JsonResponse
    {
        return $this->successResponse(CmsPage::query()->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', 'unique:cms_pages,slug'],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']).'-'.Str::random(4);
        }
        $page = CmsPage::query()->create($data);

        return $this->successResponse($page, 'صفحه ایجاد شد.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $page = CmsPage::query()->findOrFail($id);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'slug' => ['sometimes', 'string', 'max:200', 'unique:cms_pages,slug,'.$id],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        $page->update($data);

        return $this->successResponse($page->fresh(), 'صفحه به‌روزرسانی شد.');
    }

    public function destroy(int $id): JsonResponse
    {
        CmsPage::query()->findOrFail($id)->delete();

        return $this->successResponse(null, 'صفحه حذف شد.');
    }
}
