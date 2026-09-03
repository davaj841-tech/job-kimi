<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use App\Services\Security\TurnstileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TurnstileVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function enableTurnstile(): void
    {
        config([
            'services.turnstile.secret' => 'test-secret-value',
            'services.turnstile.site_key' => '0x4AAAAAAEiAUvpbmcvevZFU',
            'services.turnstile.hostnames' => ['localhost'],
            'services.turnstile.enabled' => false,
            'app.url' => 'http://localhost',
        ]);
        Setting::set('turnstile_enabled', 'true', 'security');
        Setting::set('turnstile_site_key', '0x4AAAAAAEiAUvpbmcvevZFU', 'security');
        Cache::forget('public_settings_payload');
    }

    public function test_public_settings_never_expose_secret(): void
    {
        $this->enableTurnstile();

        $response = $this->getJson('/api/v1/settings/public')->assertOk();
        $data = $response->json('data');

        $this->assertSame('turnstile', $data['captcha_mode']);
        $this->assertTrue($data['turnstile_enabled']);
        $this->assertSame('0x4AAAAAAEiAUvpbmcvevZFU', $data['turnstile_site_key']);
        $this->assertArrayNotHasKey('turnstile_secret_key', $data);
        $encoded = json_encode($data);
        $this->assertStringNotContainsString('test-secret-value', (string) $encoded);
    }

    public function test_when_turnstile_disabled_math_captcha_is_used(): void
    {
        config([
            'services.turnstile.secret' => 'test-secret-value',
            'services.turnstile.site_key' => '0x4AAAAAAEiAUvpbmcvevZFU',
        ]);
        Setting::set('turnstile_enabled', 'false', 'security');
        Cache::forget('public_settings_payload');

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('data.captcha_mode', 'math')
            ->assertJsonPath('data.turnstile_enabled', false)
            ->assertJsonPath('data.turnstile_site_key', '');

        Http::fake();

        $this->postJson('/api/v1/auth/login', [
            'login' => '09121111111',
            'password' => 'password',
            'turnstile_token' => 'should-be-ignored',
        ])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_successful_turnstile_verification(): void
    {
        $this->enableTurnstile();

        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
                'action' => 'login',
                'challenge_ts' => now()->toIso8601String(),
            ], 200),
        ]);

        // Will fail auth credentials but must pass captcha (not 422 captcha).
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '09121111111',
            'password' => 'WrongPassword1!',
            'turnstile_token' => 'valid-token',
        ]);

        $this->assertNotSame(
            'تایید امنیتی (کپچا) الزامی است.',
            $response->json('message')
        );
        $this->assertNotSame(
            'تایید امنیتی ناموفق بود. دوباره تلاش کنید.',
            $response->json('message')
        );
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'siteverify')
                && $request['secret'] === 'test-secret-value'
                && $request['response'] === 'valid-token'
                && ! str_contains(json_encode($request->data()), '0x4AAAA');
        });
    }

    public function test_failed_turnstile_verification(): void
    {
        $this->enableTurnstile();

        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ], 200),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => '09121111111',
            'password' => 'WrongPassword1!',
            'turnstile_token' => 'bad-token',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'تایید امنیتی ناموفق بود. دوباره تلاش کنید.');
    }

    public function test_expired_challenge_is_rejected(): void
    {
        $this->enableTurnstile();

        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
                'action' => 'login',
                'challenge_ts' => now()->subMinutes(30)->toIso8601String(),
            ], 200),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => '09121111111',
            'password' => 'WrongPassword1!',
            'turnstile_token' => 'old-token',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'تایید امنیتی منقضی شده است. دوباره تلاش کنید.');
    }

    public function test_invalid_hostname_is_rejected(): void
    {
        $this->enableTurnstile();

        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'evil.example',
                'action' => 'login',
                'challenge_ts' => now()->toIso8601String(),
            ], 200),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => '09121111111',
            'password' => 'WrongPassword1!',
            'turnstile_token' => 'token',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'تایید امنیتی نامعتبر است.');
    }

    public function test_action_mismatch_is_rejected(): void
    {
        $this->enableTurnstile();

        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
                'action' => 'register',
                'challenge_ts' => now()->toIso8601String(),
            ], 200),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => '09121111111',
            'password' => 'WrongPassword1!',
            'turnstile_token' => 'token',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'تایید امنیتی نامعتبر است.');
    }

    public function test_upstream_timeout_returns_safe_message(): void
    {
        $this->enableTurnstile();

        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('timeout');
            },
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => '09121111111',
            'password' => 'WrongPassword1!',
            'turnstile_token' => 'token',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'ارتباط با سرویس امنیتی برقرار نشد. دوباره تلاش کنید.');
    }

    public function test_contact_requires_turnstile_when_enabled(): void
    {
        $this->enableTurnstile();
        \Illuminate\Support\Facades\Mail::fake();

        $this->postJson('/api/v1/contact', [
            'name' => 'علی',
            'mobile' => '09123456789',
            'email' => 'ali@example.com',
            'subject' => 'support',
            'message' => 'سلام پیام تستی برای تماس',
        ])->assertStatus(422);

        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
                'action' => 'contact',
                'challenge_ts' => now()->toIso8601String(),
            ], 200),
        ]);

        $this->postJson('/api/v1/contact', [
            'name' => 'علی',
            'mobile' => '09123456789',
            'email' => 'ali@example.com',
            'subject' => 'support',
            'message' => 'سلام پیام تستی برای تماس',
            'turnstile_token' => 'ok',
        ])->assertOk();
    }

    public function test_secret_is_not_read_from_database_settings(): void
    {
        config([
            'services.turnstile.secret' => '',
            'services.turnstile.site_key' => '0x4AAAAAAEiAUvpbmcvevZFU',
            'app.url' => 'http://localhost',
        ]);
        Setting::set('turnstile_enabled', 'true', 'security');
        Setting::set('turnstile_site_key', '0x4AAAAAAEiAUvpbmcvevZFU', 'security');
        Setting::set('turnstile_secret_key', 'db-secret-must-be-ignored', 'security');

        $service = app(TurnstileService::class);
        $this->assertFalse($service->isEnabled());
        $this->assertSame('', $service->secret());
    }

    public function test_admin_cannot_persist_turnstile_secret_key(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        \Laravel\Sanctum\Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/settings', [
            'group' => 'security',
            'values' => [
                'turnstile_enabled' => true,
                'turnstile_site_key' => '0x4AAAAAAEiAUvpbmcvevZFU',
                'turnstile_secret_key' => 'should-not-save',
            ],
        ])->assertOk();

        $this->assertNull(
            \App\Models\Setting::query()->where('key', 'turnstile_secret_key')->where('value', 'should-not-save')->first()
        );
        // Even if a legacy row existed, verification must ignore it:
        Setting::set('turnstile_secret_key', 'legacy-db-secret', 'security');
        config(['services.turnstile.secret' => '']);
        $this->assertSame('', app(TurnstileService::class)->secret());
    }
}
