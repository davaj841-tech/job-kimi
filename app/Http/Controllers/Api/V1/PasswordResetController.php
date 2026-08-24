<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Services\Auth\OtpAuthService;
use App\Services\MailConfigService;
use App\Support\IranMobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends BaseController
{
    public function __construct(
        protected MailConfigService $mail,
        protected OtpAuthService $otpAuthService
    ) {}

    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:191'],
        ], [
            'identifier.required' => 'ایمیل یا شماره موبایل الزامی است.',
        ]);

        $identifier = trim($data['identifier']);
        $mobile = IranMobile::normalize($identifier);

        if ($mobile) {
            $user = User::query()->where('mobile', $mobile)->first();
            if (! $user) {
                return $this->successResponse(['channel' => 'mobile'], 'در صورت وجود حساب، کد تایید ارسال شد.');
            }

            $result = $this->otpAuthService->sendOtp($mobile);
            if (! $result['success']) {
                return $this->errorResponse($result['message'], $result['http'] ?? 429);
            }

            return $this->successResponse([
                'channel' => 'mobile',
                'expires_in' => $result['expires_in'] ?? 120,
            ], 'کد تایید به موبایل شما ارسال شد.');
        }

        if (! filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('ایمیل یا شماره موبایل معتبر نیست.', 422);
        }

        $user = User::query()->where('email', $identifier)->first();
        if ($user) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $url = rtrim(config('app.url'), '/').'/reset-password?token='.$token.'&email='.urlencode($user->email);
            $this->mail->queueTo($user->email, new PasswordResetMail($url, $user->name, 60));
        }

        return $this->successResponse(['channel' => 'email'], 'در صورت وجود حساب، لینک بازنشانی به ایمیل ارسال شد.');
    }

    public function verifyOtpReset(Request $request): JsonResponse
    {
        if ($request->filled('mobile')) {
            $normalized = IranMobile::normalize((string) $request->input('mobile'));
            if ($normalized) {
                $request->merge(['mobile' => $normalized]);
            }
        }

        $data = $request->validate([
            'mobile' => ['required', 'regex:/^09\d{9}$/'],
            'code' => ['required', 'string', 'min:4', 'max:6'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ], [
            'mobile.regex' => 'شماره موبایل نامعتبر است.',
            'password.confirmed' => 'تکرار رمز عبور مطابقت ندارد.',
        ]);

        $verify = $this->otpAuthService->verifyOtp($data['mobile'], $data['code']);
        if (! ($verify['success'] ?? false)) {
            return $this->errorResponse($verify['message'] ?? 'کد نامعتبر است.', $verify['http'] ?? 422);
        }

        /** @var User $user */
        $user = $verify['user'];
        $user->update(['password' => $data['password']]);

        // revoke token created by OTP verify — user should login with password
        $user->tokens()->delete();

        return $this->successResponse(null, 'رمز عبور با موفقیت تغییر کرد. اکنون وارد شوید.');
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $row = DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        if (! $row || ! Hash::check($data['token'], $row->token)) {
            return $this->errorResponse('لینک بازنشانی نامعتبر است.', 422);
        }

        if ($row->created_at && Carbon::parse($row->created_at)->lt(now()->subMinutes(60))) {
            return $this->errorResponse('لینک بازنشانی منقضی شده است.', 422);
        }

        $user = User::query()->where('email', $data['email'])->first();
        if (! $user) {
            return $this->errorResponse('کاربر یافت نشد.', 404);
        }

        $user->update(['password' => $data['password']]);
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return $this->successResponse(null, 'رمز عبور با موفقیت تغییر کرد.');
    }
}
