<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\OtpAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends BaseController
{
    public function __construct(
        protected OtpAuthService $otpAuthService
    ) {}

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

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->otpAuthService->verifyOtp(
            $request->validated('mobile'),
            $request->validated('code'),
            $request->validated('province')
        );

        if (! $result['success']) {
            $code = ($result['code'] ?? null) === 'PROVINCE_REQUIRED' ? 422 : 422;

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'code' => $result['code'] ?? null,
                'errors' => ($result['code'] ?? null) === 'PROVINCE_REQUIRED'
                    ? ['province' => ['انتخاب استان الزامی است.']]
                    : null,
            ], $code);
        }

        return $this->successResponse([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ], $result['message']);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ], [
            'login.required' => 'نام کاربری یا ایمیل الزامی است.',
            'password.required' => 'رمز عبور الزامی است.',
        ]);

        $login = trim($data['login']);
        $user = User::query()
            ->where(function ($q) use ($login) {
                $q->where('username', $login)->orWhere('email', $login);
            })
            ->first();

        if (! $user || blank($user->password) || ! Hash::check($data['password'], $user->password)) {
            return $this->errorResponse('نام کاربری یا رمز عبور اشتباه است.', 401);
        }

        if (($user->status ?? 'active') !== 'active') {
            return $this->errorResponse('حساب کاربری غیرفعال است.', 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load('subscriptionPlan');

        return $this->successResponse([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'ورود موفق.');
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]{3,20}$/', 'unique:users,username'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'mobile' => ['nullable', 'regex:/^09\d{9}$/', 'unique:users,mobile'],
            'email' => ['nullable', 'email', 'max:191', 'unique:users,email'],
            'province' => ['required', 'string', 'max:100'],
        ], [
            'username.regex' => 'نام کاربری فقط حروف انگلیسی، عدد و _ (۳ تا ۲۰ کاراکتر).',
            'username.unique' => 'این نام کاربری قبلاً ثبت شده است.',
            'mobile.regex' => 'شماره موبایل معتبر نیست (مثال: 09123456789).',
            'mobile.unique' => 'این شماره موبایل قبلاً ثبت شده است.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
            'password.confirmed' => 'تکرار رمز عبور مطابقت ندارد.',
            'province.required' => 'انتخاب استان الزامی است.',
        ]);

        if (empty($data['mobile']) && empty($data['email'])) {
            return $this->errorResponse('حداقل یکی از موبایل یا ایمیل الزامی است.', 422);
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'username' => strtolower($data['username']),
            'password' => $data['password'],
            'mobile' => $data['mobile'] ?? null,
            'email' => $data['email'] ?? null,
            'province' => $data['province'],
            'role' => 'jobseeker',
            'status' => 'active',
            'is_verified' => true,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => new UserResource($user->load('subscriptionPlan')),
        ], 'عضویت با موفقیت انجام شد.', 201);
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

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'province' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'nullable', 'email', 'max:191', 'unique:users,email,'.$user->id],
        ], [
            'province.required' => 'انتخاب استان الزامی است.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
        ]);

        $user->update($data);

        return $this->successResponse(new UserResource($user->fresh()->load('subscriptionPlan')), 'پروفایل به‌روزرسانی شد.');
    }
}
