<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponAdminController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::query()->latest();

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $query->where('code', 'like', '%'.strtoupper($request->query('search')).'%');
        }

        $items = $query->paginate((int) $request->query('per_page', 20));

        return $this->successResponse([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['code'] = strtoupper($data['code']);
        $data['created_by'] = $request->user()->id;
        $coupon = Coupon::query()->create($data);

        return $this->successResponse($coupon, 'کد تخفیف ایجاد شد.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $coupon = Coupon::query()->findOrFail($id);
        $data = $this->validated($request, false);
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }
        $coupon->update($data);

        return $this->successResponse($coupon->fresh(), 'کد تخفیف به‌روزرسانی شد.');
    }

    public function destroy(int $id): JsonResponse
    {
        Coupon::query()->findOrFail($id)->delete();

        return $this->successResponse(null, 'کد تخفیف حذف شد.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, bool $require = true): array
    {
        return $request->validate([
            'code' => [$require ? 'required' : 'sometimes', 'string', 'max:50', $require ? 'unique:coupons,code' : 'unique:coupons,code,'.$request->route('id')],
            'type' => [$require ? 'required' : 'sometimes', 'in:percentage,fixed'],
            'value' => [$require ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'applicable_to' => [$require ? 'required' : 'sometimes', 'in:subscription,pdf,both'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
