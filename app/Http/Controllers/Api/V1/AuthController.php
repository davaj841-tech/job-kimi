<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AvatarStorageService;
use App\Services\Auth\OtpAuthService;
use App\Support\IranMobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends BaseController
{
    public function __construct(
        protected OtpAuthService $otpAuthService,
        protected AvatarStorageService $avatars
    ) {}

    /**
     * ارسال کد یکبارمصرف (OTP)
     *
     * ارسال رمز یکبارمصرف به شماره موبایل کاربر.
     *
     * @group احراز هویت
     *
     * @unauthenticated
     *
     * @bodyParam mobile string required شماره موبایل کاربر. Example: 09123456789
     *
     * @response 200 {"success":true,"message":"کد تأیید ارسال شد.","data":{"expires_in":120}}
     * @response 422 {"success":false,"message":"شماره موبایل نامعتبر است."}
     * @response 429 {"success":false,"message":"لطفاً کمی بعد دوباره تلاش کنید."}
     */
    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $result = $this->otpAuthService->sendOtp($request->validated('mobile'));

        if (! $result['success']) {
            return $this->errorResponse($result['message'], $result['http'] ?? 429);
        }

        return $this->successResponse([
            'expires_in' => $result['expires_in'],
        ], $result['message']);
    }

    /**
     * تأیید OTP و ورود
     *
     * تأیید کد یکبارمصرف و دریافت توکن Sanctum.
     *
     * @group احراز هویت
     *
     * @unauthenticated
     *
     * @bodyParam mobile string required شماره موبایل. Example: 09123456789
     * @bodyParam code string required کد تأیید. Example: 123456
     * @bodyParam province string استان (برای کاربران جدید). Example: تهران
     *
     * @response 200 {"success":true,"message":"ورود موفق.","data":{"token":"...","user":{}}}
     * @response 422 {"success":false,"message":"کد تأیید نامعتبر است.","code":null}
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->otpAuthService->verifyOtp(
            $request->validated('mobile'),
            $request->validated('code'),
            $request->validated('province')
        );

        if (! $result['success']) {
            return $this->errorResponse($result['message'], $result['http'] ?? 422);
        }

        return $this->successResponse([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ], $result['message']);
    }

    /**
     * ورود با رمز عبور
     *
     * ورود با نام کاربری یا ایمیل و رمز عبور.
     *
     * @group احراز هویت
     *
     * @unauthenticated
     *
     * @bodyParam login string required نام کاربری یا ایمیل. Example: user@example.com
     * @bodyParam password string required رمز عبور. Example: secret123
     *
     * @response 200 {"success":true,"message":"ورود موفق.","data":{"token":"...","user":{}}}
     * @response 401 {"success":false,"message":"نام کاربری یا رمز عبور اشتباه است."}
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ], [
            'login.required' => 'نام کاربری، ایمیل یا موبایل الزامی است.',
            'password.required' => 'رمز عبور الزامی است.',
        ]);

        $login = trim($data['login']);
        $mobile = IranMobile::normalize($login);

        $user = User::query()
            ->where(function ($q) use ($login, $mobile) {
                $q->where('username', $login)->orWhere('email', $login);
                if ($mobile) {
                    $q->orWhere('mobile', $mobile);
                }
            })
            ->first();

        if ($user && $this->otpAuthService->isLocked($user)) {
            return $this->errorResponse('حساب موقتاً قفل شده است. لطفاً ۱۵ دقیقه دیگر تلاش کنید.', 423);
        }

        if (! $user || blank($user->password) || ! Hash::check($data['password'], $user->password)) {
            if ($user) {
                $this->otpAuthService->registerFailedAttempt($user);
            }

            return $this->errorResponse('نام کاربری یا رمز عبور اشتباه است.', 401);
        }

        if (! $user->isActiveAccount()) {
            return $this->errorResponse('حساب کاربری غیرفعال است.', 403);
        }

        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $token = $this->otpAuthService->issueToken($user, 'api');
        $user->load('subscriptionPlan');

        return $this->successResponse([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'ورود موفق.');
    }

    /**
     * عضویت
     *
     * ثبت‌نام کاربر جدید با نام کاربری و رمز عبور.
     *
     * @group احراز هویت
     *
     * @unauthenticated
     *
     * @bodyParam name string required نام. Example: علی رضایی
     * @bodyParam username string required نام کاربری. Example: ali_reza
     * @bodyParam password string required رمز عبور. Example: secret123
     * @bodyParam password_confirmation string required تکرار رمز. Example: secret123
     * @bodyParam province string required استان. Example: تهران
     * @bodyParam mobile string شماره موبایل. Example: 09123456789
     * @bodyParam email string ایمیل. Example: ali@example.com
     *
     * @response 201 {"success":true,"message":"عضویت با موفقیت انجام شد.","data":{"token":"...","user":{}}}
     * @response 422 {"success":false,"message":"حداقل یکی از موبایل یا ایمیل الزامی است."}
     */
    public function register(Request $request): JsonResponse
    {
        if ($request->filled('mobile')) {
            $request->merge(['mobile' => IranMobile::normalize((string) $request->input('mobile'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]{3,20}$/', 'unique:users,username'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'mobile' => ['nullable', 'regex:/^09\d{9}$/'],
            'email' => ['nullable', 'email', 'max:191', 'unique:users,email'],
            'province' => ['nullable', 'string', 'max:100'],
        ], [
            'username.regex' => 'نام کاربری فقط حروف انگلیسی، عدد و _ (۳ تا ۲۰ کاراکتر).',
            'username.unique' => 'این نام کاربری قبلاً ثبت شده است.',
            'mobile.regex' => 'شماره موبایل معتبر نیست (مثال: 09123456789 یا +989123456789).',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
            'password.confirmed' => 'تکرار رمز عبور مطابقت ندارد.',
        ]);

        if (empty($data['mobile']) && empty($data['email'])) {
            return $this->errorResponse('حداقل یکی از موبایل یا ایمیل الزامی است.', 422);
        }

        $username = strtolower($data['username']);
        $existingByMobile = ! empty($data['mobile'])
            ? User::query()->where('mobile', $data['mobile'])->first()
            : null;

        if ($existingByMobile && (filled($existingByMobile->username) || filled($existingByMobile->password))) {
            return $this->errorResponse('این شماره موبایل قبلاً ثبت شده است.', 422, [
                'mobile' => ['این شماره موبایل قبلاً ثبت شده است.'],
            ]);
        }

        $payload = [
            'name' => $data['name'],
            'username' => $username,
            'password' => $data['password'],
            'email' => $data['email'] ?? null,
            'province' => $data['province'] ?? null,
            'role' => 'jobseeker',
            'status' => 'active',
            'is_verified' => empty($data['mobile']),
        ];

        if ($existingByMobile) {
            $existingByMobile->update($payload);
            $user = $existingByMobile->fresh();
        } else {
            if (! empty($data['mobile'])) {
                $payload['mobile'] = $data['mobile'];
            } else {
                do {
                    $payload['mobile'] = '08'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
                } while (User::query()->where('mobile', $payload['mobile'])->exists());
            }
            $user = User::query()->create($payload);
        }

        if (! empty($data['mobile'])) {
            $otp = $this->otpAuthService->sendOtp($data['mobile']);
            if (! $otp['success']) {
                return $this->errorResponse($otp['message'], $otp['http'] ?? 429);
            }

            return $this->successResponse([
                'needs_otp' => true,
                'mobile' => $data['mobile'],
                'expires_in' => $otp['expires_in'] ?? 120,
                'token' => null,
                'user' => new UserResource($user->load('subscriptionPlan')),
            ], 'حساب ساخته شد. کد تایید به موبایل شما ارسال شد.', 201);
        }

        $token = $this->otpAuthService->issueToken($user, 'api');

        return $this->successResponse([
            'needs_otp' => false,
            'token' => $token,
            'user' => new UserResource($user->load('subscriptionPlan')),
        ], 'عضویت با موفقیت انجام شد.', 201);
    }

    /**
     * خروج
     *
     * باطل کردن توکن فعلی.
     *
     * @group احراز هویت
     *
     * @authenticated
     *
     * @response 200 {"success":true,"message":"خروج موفق.","data":null}
     */
    public function logout(Request $request): JsonResponse
    {
        $this->otpAuthService->logout($request->user(), $request);

        return $this->successResponse(null, 'خروج موفق.');
    }

    /**
     * پروفایل فعلی
     *
     * دریافت اطلاعات کاربر لاگین‌شده.
     *
     * @group احراز هویت
     *
     * @authenticated
     *
     * @response 200 {"success":true,"data":{}}
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('subscriptionPlan');

        return $this->successResponse(new UserResource($user));
    }

    /**
     * به‌روزرسانی پروفایل
     *
     * @group احراز هویت
     *
     * @authenticated
     *
     * @bodyParam name string نام. Example: علی رضایی
     * @bodyParam province string استان. Example: تهران
     * @bodyParam email string ایمیل. Example: ali@example.com
     *
     * @response 200 {"success":true,"message":"پروفایل به‌روزرسانی شد.","data":{}}
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'email' => ['sometimes', 'nullable', 'email', 'max:191', 'unique:users,email,'.$user->id],
            'national_code' => ['sometimes', 'nullable', 'string', 'regex:/^\d{10}$/'],
            'home_phone' => ['sometimes', 'nullable', 'string', 'max:11'],
            'military_status' => ['sometimes', 'nullable', 'string', 'max:40'],
            'insurance_history' => ['sometimes', 'nullable', 'string', 'max:80'],
            'birth_date' => ['sometimes', 'nullable', 'string', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
            'birth_province' => ['sometimes', 'nullable', 'string', 'max:80'],
            'birth_city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'marital_status' => ['sometimes', 'nullable', 'string', 'max:30'],
            'field_of_study' => ['sometimes', 'nullable', 'string', 'max:120'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'regex:/^\d{10}$/'],
            'photo' => ['sometimes', 'nullable', 'string', 'max:400000'],
        ], [
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
            'national_code.regex' => 'کد ملی باید ۱۰ رقم باشد.',
            'postal_code.regex' => 'کد پستی باید ۱۰ رقم باشد.',
            'birth_date.regex' => 'تاریخ تولد را کامل وارد کنید.',
        ]);

        if (empty($data['province']) && ! empty($data['birth_province'])) {
            $data['province'] = $data['birth_province'];
        }

        if (array_key_exists('photo', $data)) {
            $photo = $data['photo'];
            unset($data['photo']);
            if (is_string($photo) && str_starts_with($photo, 'data:image/')) {
                $data['avatar'] = $this->avatars->storeFromDataUri($user, $photo);
            } elseif ($photo === '' || $photo === null) {
                $this->avatars->delete($user);
                $data['avatar'] = null;
            }
        }

        $user->update($data);

        return $this->successResponse(new UserResource($user->fresh()->load('subscriptionPlan')), 'پروفایل به‌روزرسانی شد.');
    }
}
