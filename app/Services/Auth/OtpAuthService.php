<?php

namespace App\Services\Auth;

use App\Events\UserRegistered;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\MailConfigService;
use App\Services\Sms\SmsService;
use App\Support\IranMobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class OtpAuthService
{
    public function __construct(
        protected SmsService $smsService,
        protected MailConfigService $mailConfigService,
        protected AuditLogService $audit
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function sendOtp(string $mobile): array
    {
        $mobile = IranMobile::normalize($mobile);
        if ($mobile === null) {
            return [
                'success' => false,
                'message' => 'شماره موبایل نامعتبر است.',
                'expires_in' => null,
                'http' => 422,
            ];
        }

        $user = User::query()->where('mobile', $mobile)->first();
        if ($user && ! $user->isActiveAccount()) {
            return [
                'success' => false,
                'message' => 'حساب کاربری غیرفعال است.',
                'expires_in' => null,
                'http' => 403,
            ];
        }
        if ($user && $this->isLocked($user)) {
            return [
                'success' => false,
                'message' => 'حساب موقتاً قفل شده است. لطفاً بعداً تلاش کنید.',
                'expires_in' => null,
                'http' => 423,
            ];
        }

        $rateKey = "otp_rate:{$mobile}";
        if (Cache::has($rateKey)) {
            return [
                'success' => false,
                'message' => 'لطفاً یک دقیقه صبر کنید و دوباره تلاش کنید.',
                'expires_in' => null,
                'http' => 429,
            ];
        }

        $dayKey = 'otp_day:'.$mobile.':'.now()->toDateString();
        if ((int) Cache::get($dayKey, 0) >= 10) {
            return [
                'success' => false,
                'message' => 'تعداد درخواست کد امروز به حد مجاز رسیده است.',
                'expires_in' => null,
                'http' => 429,
            ];
        }

        $code = (string) random_int(10000, 99999);

        $user = User::query()->firstOrCreate(
            ['mobile' => $mobile],
            [
                'role' => 'jobseeker',
                'status' => 'active',
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
                'http' => 503,
            ];
        }

        Cache::put($rateKey, true, now()->addMinute());
        Cache::put($dayKey, (int) Cache::get($dayKey, 0) + 1, now()->endOfDay());

        return [
            'success' => true,
            'message' => 'کد تایید ارسال شد.',
            'expires_in' => 120,
            'http' => 200,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyOtp(string $mobile, string $code, ?string $province = null): array
    {
        $mobile = IranMobile::normalize($mobile);
        if ($mobile === null) {
            return [
                'success' => false,
                'message' => 'شماره موبایل نامعتبر است.',
                'token' => null,
                'user' => null,
                'http' => 422,
            ];
        }

        $user = User::query()->where('mobile', $mobile)->first();

        if (! $user) {
            return [
                'success' => false,
                'message' => 'کاربری با این شماره یافت نشد.',
                'token' => null,
                'user' => null,
                'http' => 422,
            ];
        }

        if (! $user->isActiveAccount()) {
            return [
                'success' => false,
                'message' => 'حساب کاربری غیرفعال است.',
                'token' => null,
                'user' => null,
                'http' => 403,
            ];
        }

        if ($this->isLocked($user)) {
            return [
                'success' => false,
                'message' => 'حساب موقتاً قفل شده است. لطفاً ۱۵ دقیقه دیگر تلاش کنید.',
                'token' => null,
                'user' => null,
                'http' => 423,
            ];
        }

        if ($user->otp_code === null || $user->otp_code === '') {
            return [
                'success' => false,
                'message' => 'کد تایید نامعتبر است یا قبلاً استفاده شده است.',
                'token' => null,
                'user' => null,
                'http' => 422,
            ];
        }

        if ($user->otp_expires_at === null || $user->otp_expires_at->isPast()) {
            $this->registerFailedAttempt($user);

            return [
                'success' => false,
                'message' => 'کد تایید منقضی شده است. دوباره درخواست کنید.',
                'token' => null,
                'user' => null,
                'http' => 422,
            ];
        }

        if (! $this->otpMatches($user->otp_code, $code)) {
            $this->registerFailedAttempt($user);

            return [
                'success' => false,
                'message' => 'کد تایید نادرست است.',
                'token' => null,
                'user' => null,
                'http' => 422,
            ];
        }

        $wasUnverified = ! $user->is_verified;

        DB::transaction(function () use ($user, $province) {
            $payload = [
                'is_verified' => true,
                'otp_code' => null,
                'otp_expires_at' => null,
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ];
            if (filled($province) && blank($user->province)) {
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
        $token = $this->issueToken($user, 'api');

        $this->audit->log('user.login', $user, null, ['mobile' => $user->mobile], $user->id);

        if ($wasUnverified) {
            event(new UserRegistered($user));
        }

        return [
            'success' => true,
            'message' => 'ورود موفق.',
            'token' => $token,
            'user' => $user,
            'http' => 200,
        ];
    }

    public function logout(User $user, ?Request $request = null): void
    {
        $token = $user->currentAccessToken();
        $tokenId = $token instanceof PersonalAccessToken ? (int) $token->id : null;
        app(LoginSessionService::class)->end($user, $tokenId);

        $this->audit->log('user.logout', $user, null, null, $user->id);
        $this->revokeCurrentToken($user, $request);
    }

    public function revokeCurrentToken(User $user, ?Request $request = null): void
    {
        $current = $user->currentAccessToken();
        if ($current instanceof PersonalAccessToken) {
            $current->delete();

            return;
        }

        $plain = $request?->bearerToken();
        if (! is_string($plain) || ! str_contains($plain, '|')) {
            return;
        }

        [$id] = explode('|', $plain, 2);
        if (ctype_digit($id)) {
            $user->tokens()->whereKey($id)->delete();
        }
    }

    public function issueToken(User $user, string $name = 'api'): string
    {
        $minutes = (int) (config('sanctum.expiration') ?: (60 * 24 * 30));
        $newToken = $user->createToken($name, ['*'], now()->addMinutes($minutes));
        app(LoginSessionService::class)->start(
            $user,
            (int) $newToken->accessToken->id,
            $name === 'api' ? 'api' : $name
        );

        return $newToken->plainTextToken;
    }

    public function registerFailedAttempt(User $user): void
    {
        $attempts = (int) $user->failed_login_attempts + 1;
        $payload = ['failed_login_attempts' => $attempts];
        if ($attempts >= 5) {
            $payload['locked_until'] = now()->addMinutes(15);
            $payload['failed_login_attempts'] = 0;
        }
        $user->update($payload);
    }

    public function isLocked(User $user): bool
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
