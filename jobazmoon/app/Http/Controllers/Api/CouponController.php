<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends BaseController
{
    public function __construct(
        protected CouponService $couponService
    ) {}

    public function validateCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'integer', 'min:0'],
            'type' => ['required', 'in:subscription,pdf'],
        ]);

        $result = $this->couponService->validate($data['code'], (int) $data['amount'], $data['type']);

        if (! $result['valid']) {
            return $this->errorResponse($result['message'], 422);
        }

        return $this->successResponse([
            'valid' => true,
            'discount_amount' => $result['discount_amount'],
            'final_amount' => $result['final_amount'],
            'message' => $result['message'],
            'code' => strtoupper(trim($data['code'])),
        ]);
    }
}
