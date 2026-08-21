<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerAdminController extends BaseController
{
    public function index(): JsonResponse
    {
        return $this->successResponse(
            Banner::query()->orderBy('position')->orderBy('sort_order')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            $data['image'] = Storage::disk('public')->url($request->file('image')->store('banners', 'public'));
        }
        $banner = Banner::query()->create($data);

        return $this->successResponse($banner, 'بنر ایجاد شد.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $banner = Banner::query()->findOrFail($id);
        $data = $this->validated($request, false);
        if ($request->hasFile('image')) {
            $data['image'] = Storage::disk('public')->url($request->file('image')->store('banners', 'public'));
        }
        $banner->update($data);

        return $this->successResponse($banner->fresh(), 'بنر به‌روزرسانی شد.');
    }

    public function destroy(int $id): JsonResponse
    {
        Banner::query()->findOrFail($id)->delete();

        return $this->successResponse(null, 'بنر حذف شد.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, bool $require = true): array
    {
        return $request->validate([
            'title' => [$require ? 'required' : 'sometimes', 'string', 'max:200'],
            'link' => ['nullable', 'string', 'max:500', 'regex:/^(https?:\/\/|\/)/'],
            'position' => [$require ? 'required' : 'sometimes', 'in:home_top,home_middle,home_hero,exam_sidebar'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
    }
}
