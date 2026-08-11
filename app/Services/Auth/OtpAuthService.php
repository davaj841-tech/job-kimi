<?php

namespace App\Services\Auth;

use App\Events\UserRegistered;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\MailConfigService;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OtpAuthService
{
    public function __construct(
        protected SmsService $smsService,
        protected MailConfigService $mailConfigService,
        protected AuditLogService $audit
    ) {}

    public function sendOtp(string $mobile): array
    {
        $user = User::query()->where('mobile', $mobile)->first();
        if ($user && $this->isLocked($user)) {
            return [
                'success' => false,
                'message' => 'حساب موقتاً قفل شده است. لطفاً بعداً تلاش کنید.',
                'expires_in' => null,
            ];
        }

        $rateKey = "otp_rate:{$mobile}";

        if (Cache::has($rateKey)) {
            return [
                'success' => false,
                'message' => 'لطفاً یک دقیقه صبر کنید و دوباره تلاش کنید.',
                'expires_in' => null,
            ];
        }

        $code = (string) random_int(10000, 99999);

        $user = User::query()->firstOrCreate(
            ['mobile' => $mobile],
            [
                'role' => 'jobseeker',
                'is_verified' => false,
            ]
        );

        $user->update([
            'otp_code' => $this->hashOtp($code),
            'otp_expires_at' => now()->addMinutes(2),
        ]);

        $sent = $this->smsService->sendOtp($mobile, $code);

        if (! $sent) {
            return [
                'success' => false,
                'message' => 'ارسال پیامک ناموفق بود. لطفاً دوباره تلاش کنید.',
                'expires_in' => null,
            ];
        }

        Cache::put($rateKey, true, now()->addMinute());

        return [
            'success' => true,
            'message' => 'کد تایید ارسال شد.',
            'expires_in' => 120,
        ];
    }

    public function verifyOtp(string $mobile, string $code, ?string $province = null): array
    {
        $user = User::query()->where('mobile', $mobile)->first();

        if (! $user) {
            return [
                'success' => false,
                'message' => 'کاربری با این شماره یافت نشد.',
                'token' => null,
                'user' => null,
            ];
        }

        if ($this->isLocked($user)) {
            return [
                'success' => false,
                'message' => 'حساب موقتاً قفل شده است. لطفاً ۱۵ دقیقه دیگر تلاش کنید.',
                'token' => null,
                'user' => null,
            ];
        }

        if (! $this->otpMatches($user->otp_code, $code) || $user->otp_expires_at === null || $user->otp_expires_at->isPast()) {
            $attempts = (int) $user->failed_login_attempts + 1;
            $payload = ['failed_login_attempts' => $attempts];
            if ($attempts >= 5) {
                $payload['locked_until'] = now()->addMinutes(15);
                $payload['failed_login_attempts'] = 0;
            }
            $user->update($payload);

            return [
                'success' => false,
                'message' => 'کد تایید نامعتبر یا منقضی شده است.',
                'token' => null,
                'user' => null,
            ];
        }

        $wasUnverified = ! $user->is_verified;
        $needsProvince = blank($user->province);
        if ($needsProvince && blank($province)) {
            return [
                'success' => false,
                'message' => 'برای ورود اولیه انتخاب استان الزامی است.',
                'code' => 'PROVINCE_REQUIRED',
                'token' => null,
                'user' => null,
            ];
        }

        DB::transaction(function () use ($user, $province, $needsProvince) {
            $payload = [
                'is_verified' => true,
                'otp_code' => null,
                'otp_expires_at' => null,
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ];
            if ($needsProvince || (filled($province) && blank($user->province))) {
                $payload['province'] = $province;
            }
            $user->update($payload);

            if ($user->subscription_plan_id === null) {
                $freePlan = SubscriptionPlan::query()->where('price', 0)->first();
                if ($freePlan) {
                    $user->update(['subscription_plan_id' => $freePlan->id]);
                }
            }
        });

        $user->refresh()->load('subscriptionPlan');
        $token = $user->createToken('api')->plainTextToken;

        $this->audit->log('user.login', $user, null, ['mobile' => $user->mobile], $user->id);

        if ($wasUnverified) {
            event(new UserRegistered($user));
        }

        return [
            'success' => true,
            'message' => 'ورود موفق.',
            'token' => $token,
            'user' => $user,
        ];
    }

    public function logout(User $user): void
    {
        $this->audit->log('user.logout', $user, null, null, $user->id);
        $user->currentAccessToken()?->delete();
    }

    protected function isLocked(User $user): bool
    {
        return $user->locked_until !== null && $user->locked_until->isFuture();
    }

    protected function hashOtp(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    protected function otpMatches(?string $stored, string $code): bool
    {
        if ($stored === null || $stored === '') {
            return false;
        }

        if (hash_equals($stored, $this->hashOtp($code))) {
            return true;
        }

        // Legacy plaintext codes (pre-hash column / in-flight OTPs).
        return hash_equals($stored, $code);
    }
}
