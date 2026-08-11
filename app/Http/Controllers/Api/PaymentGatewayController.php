<?php

namespace App\Http\Controllers\Api;

use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentGatewayController extends BaseController
{
    public function __construct(protected PaymentService $payments) {}

    public function index(): JsonResponse
    {
        return $this->successResponse($this->payments->activeGateways());
    }
}
