<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\OtpAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function __construct(
        protected OtpAuthService $otpAuthService
    ) {}

/**
 * @OA\Post(
 *   path="/auth/otp/send",
 *   tags={"Auth"},
 *   summary="Send OTP",
 *   @OA\RequestBody(required=true, @OA\JsonContent(required={"mobile"}, @OA\Property(property="mobile", type="string"))),
 *   @OA\Response(response=200, description="OTP sent"),
 *   @OA\Response(response=422, description="Validation error"),
 *   @OA\Response(response=429, description="Rate limited")
 * )
 */
    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $result = $this->otpAuthService->sendOtp($request->validated('mobile'));

        if (! $result['success']) {
            return $this->errorResponse($result['message'], 429);
        }

        return $this->successResponse([
            'expires_in' => $result['expires_in'],
        ], $result['message']);
    }

    /**
     * @OA\Post(
     *   path="/auth/otp/verify",
     *   tags={"Auth"},
     *   summary="Verify OTP",
     *   @OA\RequestBody(required=true, @OA\JsonContent(required={"mobile","code"}, @OA\Property(property="mobile", type="string"), @OA\Property(property="code", type="string"))),
     *   @OA\Response(response=200, description="Login success"),
     *   @OA\Response(response=422, description="Invalid code"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->otpAuthService->verifyOtp(
            $request->validated('mobile'),
            $request->validated('code')
        );

        if (! $result['success']) {
            return $this->errorResponse($result['message'], 422);
        }

        return $this->successResponse([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ], $result['message']);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->otpAuthService->logout($request->user());

        return $this->successResponse(null, 'خروج موفق.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('subscriptionPlan');

        return $this->successResponse(new UserResource($user));
    }
}
