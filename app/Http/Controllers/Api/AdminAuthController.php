<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Auth\OtpAuthService;
use App\Services\MailConfigService;
use App\Support\IranMobile;
use App\Support\StaffRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminAuthController extends BaseController
{
    public function __construct(
        protected AuditLogService $audit,
        protected MailConfigService $mail,
        protected OtpAuthService $otpAuthService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $login = trim($data['username']);
        if ($login === '') {
            return $this->errorResponse('شناسه ورود الزامی است.', 422);
        }

        $user = $this->findStaffUserByLogin($login);

        if ($user && $this->isLocked($user)) {
            $this->audit->log('admin.login_failed', $user, null, [
                'username' => $login,
                'reason' => 'locked',
            ], $user->id);

            return $this->errorResponse('حساب موقتاً قفل شده است. لطفاً ۱۵ دقیقه دیگر تلاش کنید.', 423);
        }

        $valid = $user
            && filled($user->password)
            && Hash::check($data['password'], $user->password)
            && ($user->status ?? 'active') === 'active';

        if (! $valid) {
            if ($user) {
                $this->registerFailedAttempt($user);
                $this->audit->log('admin.login_failed', $user, null, [
                    'username' => $login,
                    'reason' => 'invalid_credentials',
                ], $user->id);
            } else {
                $this->audit->log('admin.login_failed', null, null, [
                    'username' => $login,
                    'reason' => 'user_not_found',
                ]);
            }

            return $this->errorResponse('نام کاربری یا رمز عبور اشتباه است', 401);
        }

        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $token = $this->otpAuthService->issueToken($user, 'admin_token');
        $user->load('subscriptionPlan');

        $this->audit->log('admin.login', $user, null, [
            'username' => $user->username,
            'method' => 'password',
        ], $user->id);

        return $this->successResponse([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'ورود موفق.');
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $this->audit->log('admin.logout', $user, null, ['method' => 'password'], $user->id);
            $this->otpAuthService->revokeCurrentToken($user, $request);
        }

        return $this->successResponse(null, 'خروج موفق.');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()
            ->where('email', $data['email'])
            ->whereIn('role', StaffRoles::staffRoles())
            ->first();

        if ($user && filled($user->email)) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $url = rtrim(config('app.url'), '/').'/reset-password?token='.$token.'&email='.urlencode($user->email);
            $this->mail->queueTo($user->email, new PasswordResetMail($url, $user->name ?? 'Admin', 60));

            $this->audit->log('admin.password_reset_requested', $user, null, [
                'email' => $user->email,
            ], $user->id);
        }

        return $this->successResponse(null, 'لینک بازنشانی به ایمیل شما ارسال شد');
    }

    protected function registerFailedAttempt(User $user): void
    {
        $attempts = (int) $user->failed_login_attempts + 1;
        $payload = ['failed_login_attempts' => $attempts];

        if ($attempts >= 5) {
            $payload['locked_until'] = now()->addMinutes(15);
            $payload['failed_login_attempts'] = 0;
        }

        $user->update($payload);
    }

    protected function isLocked(User $user): bool
    {
        return $user->locked_until !== null && $user->locked_until->isFuture();
    }

    protected function findStaffUserByLogin(string $login): ?User
    {
        $base = User::query()->whereIn('role', StaffRoles::staffRoles());

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return (clone $base)->where('email', $login)->first();
        }

        if ($mobile = IranMobile::normalize($login)) {
            return (clone $base)->where('mobile', $mobile)->first();
        }

        if (preg_match('/^[a-zA-Z0-9_]{3,20}$/', $login) === 1) {
            return (clone $base)->where('username', $login)->first();
        }

        return null;
    }
}
