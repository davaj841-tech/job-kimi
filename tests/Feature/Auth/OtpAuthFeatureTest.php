<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class OtpAuthFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_otp_succeeds_for_valid_iranian_mobile(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/send', $this->withAuthCaptcha([
            'mobile' => '09121234567',
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'expires_in',
                ],
            ])
            ->assertJsonPath('data.expires_in', 180);

        $this->assertDatabaseHas('users', [
            'mobile' => '09121234567',
        ]);

        $user = User::query()->where('mobile', '09121234567')->first();
        $this->assertNotNull($user?->otp_code);
        $this->assertNotNull($user?->otp_expires_at);
    }

    public function test_send_otp_normalizes_plus98_mobile_format(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/send', $this->withAuthCaptcha([
            'mobile' => '+989121234568',
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['expires_in'],
            ]);

        $this->assertDatabaseHas('users', [
            'mobile' => '09121234568',
        ]);
    }

    public function test_send_otp_fails_for_inactive_user(): void
    {
        User::factory()->create([
            'mobile' => '09121234569',
            'status' => 'blocked',
        ]);

        $response = $this->postJson('/api/v1/auth/otp/send', $this->withAuthCaptcha([
            'mobile' => '09121234569',
        ]));

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
            ]);
    }

    public function test_send_otp_fails_when_sms_cannot_be_delivered(): void
    {
        config([
            'sms.allow_log_fallback' => false,
            'services.sms.allow_log_fallback' => false,
            'sms.melipayamak.username' => null,
            'sms.melipayamak.password' => null,
            'services.melipayamak.username' => null,
            'services.melipayamak.password' => null,
            'services.kavenegar.api_key' => null,
            'sms.kavenegar.api_key' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/otp/send', $this->withAuthCaptcha([
            'mobile' => '09121234570',
        ]));

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
            ]);
    }

    public function test_rejects_invalid_iranian_mobile_formats(): void
    {
        $this->withoutMiddleware([
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
        ]);

        $invalidMobiles = [
            '08121234567',
            '0912123456',
            '091212345678',
            'abcd',
            '12345',
            '',
        ];

        foreach ($invalidMobiles as $mobile) {
            $payload = $this->withAuthCaptcha(['mobile' => $mobile]);
            if ($mobile === '') {
                unset($payload['mobile']);
            }

            $response = $this->postJson('/api/v1/auth/otp/send', $payload);

            $response->assertStatus(422)
                ->assertJsonPath('success', false)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'errors',
                ]);
        }
    }

    public function test_login_with_valid_otp_succeeds(): void
    {
        $code = '12345';
        $user = User::factory()->create([
            'mobile' => '09121234571',
            'otp_code' => hash_hmac('sha256', $code, (string) config('app.key')),
            'otp_expires_at' => now()->addMinutes(2),
            'is_verified' => false,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/otp/verify', $this->withAuthCaptcha([
            'mobile' => '09121234571',
            'code' => $code,
            'province' => 'تهران',
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'mobile',
                        'role',
                        'is_verified',
                    ],
                ],
            ])
            ->assertJsonPath('data.user.mobile', '09121234571')
            ->assertJsonPath('data.user.is_verified', true);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNull($user->fresh()->otp_code);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_with_expired_otp_fails(): void
    {
        $code = '54321';
        User::factory()->create([
            'mobile' => '09121234572',
            'otp_code' => hash_hmac('sha256', $code, (string) config('app.key')),
            'otp_expires_at' => now()->subMinute(),
            'is_verified' => false,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/otp/verify', $this->withAuthCaptcha([
            'mobile' => '09121234572',
            'code' => $code,
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
            ])
            ->assertJsonFragment([
                'message' => 'کد تایید منقضی شده است. دوباره درخواست کنید.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_revokes_sanctum_token(): void
    {
        $user = User::factory()->create([
            'mobile' => '09121234573',
            'status' => 'active',
            'is_verified' => true,
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $logout = $this->withToken($token)->postJson('/api/v1/auth/logout');

        $logout->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
            ]);
    }

    public function test_refresh_token_issues_new_sanctum_token_and_revokes_old(): void
    {
        $user = User::factory()->create([
            'mobile' => '09121234574',
            'status' => 'active',
            'is_verified' => true,
        ]);
        $oldToken = $user->createToken('api')->plainTextToken;
        $oldTokenId = (int) explode('|', $oldToken, 2)[0];

        $response = $this->withToken($oldToken)->postJson('/api/v1/auth/refresh');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'mobile',
                    ],
                ],
            ]);

        $newToken = $response->json('data.token');
        $this->assertNotEmpty($newToken);
        $this->assertNotSame($oldToken, $newToken);

        $this->assertNull(PersonalAccessToken::query()->find($oldTokenId));
        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());

        $this->app['auth']->forgetGuards();

        $this->withToken($oldToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->withToken($newToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'mobile',
                ],
            ]);
    }

    public function test_otp_send_is_rate_limited_after_repeated_requests(): void
    {
        $mobile = '09121234575';

        $first = $this->postJson('/api/v1/auth/otp/send', $this->withAuthCaptcha([
            'mobile' => $mobile,
        ]));
        $first->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['expires_in'],
            ]);

        $second = $this->postJson('/api/v1/auth/otp/send', $this->withAuthCaptcha([
            'mobile' => $mobile,
        ]));
        $second->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
            ]);
    }

    public function test_otp_send_throttle_middleware_limits_burst_requests(): void
    {
        $mobile = '09121234576';

        // ۵ درخواست در ۱۰ دقیقه مجاز است — درخواست ششم باید ۴۲۹ شود
        for ($i = 0; $i < 5; $i++) {
            Cache::forget("otp_rate:{$mobile}");
            $this->postJson('/api/v1/auth/otp/send', $this->withAuthCaptcha([
                'mobile' => $mobile,
            ]))->assertOk();
        }

        Cache::forget("otp_rate:{$mobile}");

        $blocked = $this->postJson('/api/v1/auth/otp/send', $this->withAuthCaptcha([
            'mobile' => $mobile,
        ]));

        $blocked->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
            ]);

        $this->assertStringContainsString('تعداد درخواست‌های شما بیش از حد مجاز است', (string) $blocked->json('message'));
        $this->assertStringContainsString('دقیقه دیگر تلاش کنید', (string) $blocked->json('message'));
    }
}
