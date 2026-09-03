<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\Auth\OtpAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_otp_stores_hashed_code(): void
    {
        config(['services.sms.allow_log_fallback' => true]);

        $result = app(OtpAuthService::class)->sendOtp('09121234567');

        $this->assertTrue($result['success']);
        $user = User::query()->where('mobile', '09121234567')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->otp_code);
        $this->assertSame(64, strlen((string) $user->otp_code));
        $this->assertDoesNotMatchRegularExpression('/^\d{5}$/', (string) $user->otp_code);
    }

    public function test_verify_accepts_hashed_otp(): void
    {
        $code = '12345';
        User::factory()->create([
            'mobile' => '09129876543',
            'province' => 'تهران',
            'otp_code' => hash_hmac('sha256', $code, (string) config('app.key')),
            'otp_expires_at' => now()->addMinutes(2),
            'is_verified' => false,
        ]);

        $result = app(OtpAuthService::class)->verifyOtp('09129876543', $code, 'تهران');

        $this->assertTrue($result['success']);
        $this->assertNull(User::query()->where('mobile', '09129876543')->value('otp_code'));
    }

    public function test_verify_rejects_legacy_plaintext_otp_by_default(): void
    {
        config(['services.sms.allow_legacy_plaintext_otp' => false]);

        User::factory()->create([
            'mobile' => '09121112233',
            'province' => 'تهران',
            'otp_code' => '54321',
            'otp_expires_at' => now()->addMinutes(2),
            'is_verified' => true,
        ]);

        $result = app(OtpAuthService::class)->verifyOtp('09121112233', '54321');

        $this->assertFalse($result['success']);
    }

    public function test_verify_accepts_legacy_plaintext_otp_when_enabled(): void
    {
        config(['sms.allow_legacy_plaintext_otp' => true, 'services.sms.allow_legacy_plaintext_otp' => true]);

        User::factory()->create([
            'mobile' => '09121112234',
            'province' => 'تهران',
            'otp_code' => '54321',
            'otp_expires_at' => now()->addMinutes(2),
            'is_verified' => true,
        ]);

        $result = app(OtpAuthService::class)->verifyOtp('09121112234', '54321');

        $this->assertTrue($result['success']);
    }

    public function test_send_otp_failure_logs_without_missing_setting_class(): void
    {
        $sms = \Mockery::mock(\App\Services\Sms\SmsService::class);
        $sms->shouldReceive('sendOtpDetailed')->once()->andReturn(
            \App\Services\Sms\SmsResult::failed('melipayamak', 'otp', 'delivery_failed', 'Provider rejected request')
        );
        $this->app->instance(\App\Services\Sms\SmsService::class, $sms);

        $audit = \Mockery::mock(\App\Services\AuditLogService::class);
        $this->app->instance(\App\Services\AuditLogService::class, $audit);

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'OTP SMS delivery failed'
                    && ($context['error_code'] ?? null) === 'delivery_failed'
                    && ($context['error_message'] ?? null) === 'Provider rejected request'
                    && ! isset($context['password'])
                    && ! isset($context['code']);
            });

        $result = app(OtpAuthService::class)->sendOtp('09121234567');

        $this->assertFalse($result['success']);
        $this->assertSame(503, $result['http']);
        $this->assertStringContainsString('ارسال کد تأیید با مشکل مواجه شد', $result['message']);
    }

    public function test_send_otp_succeeds_when_sms_provider_accepts(): void
    {
        $sms = \Mockery::mock(\App\Services\Sms\SmsService::class);
        $sms->shouldReceive('sendOtpDetailed')->once()->andReturn(
            \App\Services\Sms\SmsResult::success('melipayamak', 'otp', '999', 'sent')
        );
        $this->app->instance(\App\Services\Sms\SmsService::class, $sms);

        $audit = \Mockery::mock(\App\Services\AuditLogService::class);
        $this->app->instance(\App\Services\AuditLogService::class, $audit);

        $result = app(OtpAuthService::class)->sendOtp('09121234567');

        $this->assertTrue($result['success']);
        $this->assertSame(200, $result['http']);
        $this->assertNotNull($result['expires_in']);
    }
}
