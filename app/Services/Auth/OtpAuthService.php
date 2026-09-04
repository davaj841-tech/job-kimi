<?php

namespace App\Services\Auth;

use App\Events\UserRegistered;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Sms\SmsService;
use App\Support\IranMobile;
use App\Support\SmsMobileMask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class OtpAuthService
{
    public function __construct(
        protected SmsService $smsService,
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

        $resendCooldown = max(30, (int) config('sms.otp.resend_cooldown_seconds', 60));
        $rateKey = "otp_rate:{$mobile}";
        if (Cache::has($rateKey)) {
            return [
                'success' => false,
                'message' => 'لطفاً یک دقیقه صبر کنید و دوباره تلاش کنید.',
                'expires_in' => null,
                'http' => 429,
            ];
        }

        $dailyLimit = max(1, (int) config('sms.otp.daily_limit', 10));
        $dayKey = 'otp_day:'.$mobile.':'.now()->toDateString();
        if ((int) Cache::get($dayKey, 0) >= $dailyLimit) {
            return [
                'success' => false,
                'message' => 'تعداد درخواست کد امروز به حد مجاز رسیده است.',
                'expires_in' => null,
                'http' => 429,
            ];
        }

        $length = max(4, min(8, (int) config('sms.otp.length', 5)));
        $min = (int) (10 ** ($length - 1));
        $max = (int) ((10 ** $length) - 1);
        $code = (string) random_int($min, $max);

        $expiresMinutes = max(1, (int) config('sms.otp.expires_minutes', 3));

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
            'otp_expires_at' => now()->addMinutes($expiresMinutes),
        ]);

        $delivery = $this->smsService->sendOtpDetailed($mobile, $code);

        if (! $delivery->success) {
            Log::warning('OTP SMS delivery failed', [
                'mobile' => SmsMobileMask::mask($mobile),
                'provider' => $delivery->provider ?: Setting::getFilled('sms_gateway', config('sms.provider', 'melipayamak')),
                'status' => $delivery->status,
                'error_code' => $delivery->errorCode,
                'error_message' => $delivery->errorMessage,
                'http_status' => $delivery->httpStatus,
                'provider_response' => $delivery->providerResponse,
                'skipped' => $delivery->skipped,
                'duration_ms' => $delivery->durationMs,
            ]);

            $message = match (true) {
                $delivery->skipped => 'ارسال پیامک تأیید موقتاً غیرفعال است. لطفاً بعداً تلاش کنید.',
                $delivery->errorCode === 'missing_credentials' => 'پیکربندی پیامک ناقص است. لطفاً با پشتیبانی تماس بگیرید.',
                $delivery->errorCode === 'pattern_failed_no_from',
                $delivery->errorCode === 'InvalidBodyId' => 'ارسال کد تأیید با مشکل مواجه شد. لطفاً چند لحظه دیگر دوباره تلاش کنید.',
                default => 'ارسال کد تأیید با مشکل مواجه شد. لطفاً چند لحظه دیگر دوباره تلاش کنید.',
            };

            return [
                'success' => false,
                'message' => $message,
                'expires_in' => null,
                'http' => 503,
            ];
        }

        Cache::put($rateKey, true, now()->addSeconds($resendCooldown));
        Cache::put($dayKey, (int) Cache::get($dayKey, 0) + 1, now()->endOfDay());

        return [
            'success' => true,
            'message' => 'کد تایید ارسال شد.',
            'expires_in' => $expiresMinutes * 60,
            'http' => 200,
        ];
    }

    /**
     * Verify OTP and invalidate it without issuing a login token (password reset).
     *
     * @return array{success: bool, message: string, user: ?User, http: int}
     */
    public function verifyOtpCodeOnly(string $mobile, string $code): array
    {
        $mobile = IranMobile::normalize($mobile);
        if ($mobile === null) {
            return [
                'success' => false,
                'message' => 'شماره موبایل نامعتبر است.',
                'user' => null,
                'http' => 422,
            ];
        }

        $user = User::query()->where('mobile', $mobile)->first();
        if (! $user) {
            return [
                'success' => false,
                'message' => 'کاربری با این شماره یافت نشد.',
                'user' => null,
                'http' => 422,
            ];
        }

        if (! $user->isActiveAccount()) {
            return [
                'success' => false,
                'message' => 'حساب کاربری غیرفعال است.',
                'user' => null,
                'http' => 403,
            ];
        }

        if ($this->isLocked($user)) {
            return [
                'success' => false,
                'message' => 'حساب موقتاً قفل شده است. لطفاً ۱۵ دقیقه دیگر تلاش کنید.',
                'user' => null,
                'http' => 423,
            ];
        }

        if ($user->otp_code === null || $user->otp_code === '') {
            return [
                'success' => false,
                'message' => 'کد تایید نامعتبر است یا قبلاً استفاده شده است.',
                'user' => null,
                'http' => 422,
            ];
        }

        if ($user->otp_expires_at === null || $user->otp_expires_at->isPast()) {
            $this->registerFailedAttempt($user);

            return [
                'success' => false,
                'message' => 'کد تایید منقضی شده است. دوباره درخواست کنید.',
                'user' => null,
                'http' => 422,
            ];
        }

        if (! $this->otpMatches($user->otp_code, $code)) {
            $this->registerFailedAttempt($user);

            return [
                'success' => false,
                'message' => 'کد تایید نادرست است.',
                'user' => null,
                'http' => 422,
            ];
        }

        $user->update([
            'otp_code' => null,
            'otp_expires_at' => null,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        return [
            'success' => true,
            'message' => 'کد تایید معتبر است.',
            'user' => $user->fresh(),
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
        $maxAttempts = max(1, (int) config('sms.otp.max_verify_attempts', 5));
        $lockoutMinutes = max(1, (int) config('sms.otp.lockout_minutes', 15));
        $attempts = (int) $user->failed_login_attempts + 1;
        $payload = ['failed_login_attempts' => $attempts];
        if ($attempts >= $maxAttempts) {
            $payload['locked_until'] = now()->addMinutes($lockoutMinutes);
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

        // Legacy plaintext only when explicitly enabled (migration window).
        if (config('sms.allow_legacy_plaintext_otp', config('services.sms.allow_legacy_plaintext_otp', false)) && hash_equals($stored, $code)) {
            return true;
        }

        return false;
    }
}
