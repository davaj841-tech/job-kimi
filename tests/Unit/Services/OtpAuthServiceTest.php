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

    public function test_verify_accepts_legacy_plaintext_otp(): void
    {
        User::factory()->create([
            'mobile' => '09121112233',
            'province' => 'تهران',
            'otp_code' => '54321',
            'otp_expires_at' => now()->addMinutes(2),
            'is_verified' => true,
        ]);

        $result = app(OtpAuthService::class)->verifyOtp('09121112233', '54321');

        $this->assertTrue($result['success']);
    }
}
