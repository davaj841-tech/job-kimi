<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_send_rate_limit_message_is_persian_with_minutes(): void
    {
        RateLimiter::clear(md5('otp-send'.'otp-send:127.0.0.1'));

        for ($i = 0; $i < 5; $i++) {
            Cache::forget('otp_rate:0912999000'.$i);
            $this->postJson('/api/v1/auth/otp/send', $this->withAuthCaptcha([
                'mobile' => '0912999000'.$i,
            ]));
        }

        $response = $this->postJson('/api/v1/auth/otp/send', $this->withAuthCaptcha([
            'mobile' => '09129990099',
        ]));

        $response->assertStatus(429)
            ->assertJsonPath('success', false);

        $this->assertMatchesRegularExpression(
            '/تعداد درخواست‌های شما بیش از حد مجاز است\. لطفاً \d+ دقیقه دیگر تلاش کنید\./u',
            (string) $response->json('message')
        );
    }

    public function test_payment_routes_are_limited_per_user(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        // Laravel هش می‌کند: md5(limiterName + by-key)
        $key = md5('payment'.'payment:user:'.$user->id);
        RateLimiter::clear($key);

        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit($key, 3600);
        }

        $response = $this->getJson('/api/v1/transactions');

        $response->assertStatus(429)
            ->assertJsonPath('success', false);

        $this->assertMatchesRegularExpression(
            '/تعداد درخواست‌های شما بیش از حد مجاز است\. لطفاً \d+ دقیقه دیگر تلاش کنید\./u',
            (string) $response->json('message')
        );
    }
}
