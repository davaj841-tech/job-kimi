<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\JobClassification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JobClassificationAdminController extends BaseController
{
    public function index(): JsonResponse
    {
        $items = JobClassification::query()
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $flat = JobClassification::query()
            ->with('parent:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (JobClassification $c) => $this->toFlat($c))
            ->values();

        return $this->successResponse([
            'tree' => $items,
            'flat' => $flat,
            'parents' => $flat->whereNull('parent_id')->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        unset($data['logo'], $data['remove_logo']);

        if ($request->has('parent_id') && $request->input('parent_id') === '') {
            $data['parent_id'] = null;
        }

        $item = JobClassification::query()->create([
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? $this->nextSort($data['parent_id'] ?? null),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'show_on_home' => $request->has('show_on_home') ? $request->boolean('show_on_home') : true,
            'icon' => $data['icon'] ?? 'briefcase',
            'color' => $data['color'] ?? '#1e3a5f',
            'logo_path' => $this->storeLogo($request),
        ]);

        return $this->successResponse($item->load('parent'), 'طبقه‌بندی ایجاد شد.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = JobClassification::query()->find($id);
        if (! $item) {
            return $this->errorResponse('طبقه‌بندی یافت نشد.', 404);
        }

        $data = $this->validated($request, $id);
        unset($data['logo'], $data['remove_logo']);

        if ($request->has('parent_id') && $request->input('parent_id') === '') {
            $data['parent_id'] = null;
        }

        if ($request->boolean('remove_logo')) {
            $data['logo_path'] = null;
        } elseif ($request->hasFile('logo')) {
            $data['logo_path'] = $this->storeLogo($request);
        }

        // boolean از FormData
        foreach (['is_active', 'show_on_home'] as $boolKey) {
            if ($request->has($boolKey)) {
                $data[$boolKey] = $request->boolean($boolKey);
            }
        }

        $item->update($data);

        return $this->successResponse($item->fresh()->load('parent', 'children'), 'طبقه‌بندی به‌روزرسانی شد.');
    }

    public function destroy(int $id): JsonResponse
    {
        $item = JobClassification::query()->find($id);
        if (! $item) {
            return $this->errorResponse('طبقه‌بندی یافت نشد.', 404);
        }

        if ($item->children()->exists()) {
            return $this->errorResponse('ابتدا زیرمجموعه‌های این طبقه‌بندی را حذف یا جابه‌جا کنید.', 422);
        }

        if ($item->jobPosts()->exists()) {
            return $this->errorResponse('این طبقه‌بندی در آگهی‌ها استفاده شده و قابل حذف نیست.', 422);
        }

        $item->delete();

        return $this->successResponse(null, 'طبقه‌بندی حذف شد.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'exists:job_classifications,id'],
            'direction' => ['required', 'in:up,down'],
        ]);

        $item = JobClassification::query()->findOrFail($data['id']);
        $siblings = JobClassification::query()
            ->where('parent_id', $item->parent_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $index = $siblings->search(fn ($row) => (int) $row->id === (int) $item->id);
        if ($index === false) {
            return $this->errorResponse('آیتم یافت نشد.', 404);
        }

        $swapWith = $data['direction'] === 'up' ? $index - 1 : $index + 1;
        if ($swapWith < 0 || $swapWith >= $siblings->count()) {
            return $this->successResponse(['tree' => $this->tree()], 'مرتب‌سازی بدون تغییر');
        }

        DB::transaction(function () use ($siblings, $index, $swapWith) {
            $a = $siblings[$index];
            $b = $siblings[$swapWith];
            $tmp = $a->sort_order;
            $a->sort_order = $b->sort_order;
            $b->sort_order = $tmp;
            // اگر هر دو یکسان بودند، با ایندکس جابه‌جا کن
            if ((int) $a->sort_order === (int) $b->sort_order) {
                $a->sort_order = $index;
                $b->sort_order = $swapWith;
            }
            $a->save();
            $b->save();
        });

        return $this->successResponse([
            'tree' => $this->tree(),
            'flat' => $this->flat(),
        ], 'مرتب‌سازی انجام شد.');
    }

    protected function validated(Request $request, ?int $id = null): array
    {
        $parentRules = ['nullable', 'integer', 'exists:job_classifications,id'];
        if ($id) {
            $parentRules[] = Rule::notIn([$id]);
        }

        return $request->validate([
            'name' => [
                $id ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('job_classifications', 'name')->ignore($id),
            ],
            'parent_id' => $parentRules,
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'show_on_home' => ['sometimes', 'boolean'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:30'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'نام طبقه‌بندی الزامی است.',
            'name.unique' => 'این طبقه‌بندی قبلاً ثبت شده است.',
            'parent_id.exists' => 'طبقه‌بندی مادر معتبر نیست.',
            'parent_id.not_in' => 'طبقه‌بندی نمی‌تواند مادر خودش باشد.',
        ]);
    }

    protected function storeLogo(Request $request): ?string
    {
        if (! $request->hasFile('logo')) {
            return null;
        }

        return $request->file('logo')->store('classification-logos', 'public');
    }

    protected function nextSort(?int $parentId): int
    {
        return (int) JobClassification::query()->where('parent_id', $parentId)->max('sort_order') + 1;
    }

    protected function tree()
    {
        return JobClassification::query()
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    protected function flat()
    {
        return JobClassification::query()
            ->with('parent:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (JobClassification $c) => $this->toFlat($c))
            ->values();
    }

    protected function toFlat(JobClassification $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->parent_id
                ? (($c->parent?->name ?: '').' › '.$c->name)
                : $c->name,
            'raw_name' => $c->name,
            'parent_id' => $c->parent_id,
            'icon' => $c->icon,
            'color' => $c->color,
            'logo_url' => $c->logo_url,
            'is_active' => $c->is_active,
            'show_on_home' => (bool) $c->show_on_home,
            'sort_order' => $c->sort_order,
        ];
    }
}
