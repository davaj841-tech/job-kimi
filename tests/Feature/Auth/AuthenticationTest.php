<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_otp_succeeds_and_hashes_code(): void
    {
        $res = $this->postJson('/api/v1/auth/otp/send', $this->withAuthCaptcha([
            'mobile' => '+989120000001',
        ]));

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.expires_in', 180);

        $user = User::query()->where('mobile', '09120000001')->first();
        $this->assertNotNull($user);
        $this->assertSame(64, strlen((string) $user->otp_code));
        $this->assertDoesNotMatchRegularExpression('/^\d{5}$/', (string) $user->otp_code);
    }

    public function test_verify_otp_issues_token(): void
    {
        $code = '12345';
        $this->seedHashedOtp('09120000002', $code);

        $res = $this->postJson('/api/v1/auth/otp/verify', $this->withAuthCaptcha([
            'mobile' => '09120000002',
            'code' => $code,
        ]));

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertNull(User::query()->where('mobile', '09120000002')->value('otp_code'));
    }

    public function test_expired_otp_is_rejected(): void
    {
        $code = '12345';
        User::factory()->create([
            'mobile' => '09120000003',
            'otp_code' => hash_hmac('sha256', $code, (string) config('app.key')),
            'otp_expires_at' => now()->subMinute(),
            'is_verified' => false,
        ]);

        $this->postJson('/api/v1/auth/otp/verify', $this->withAuthCaptcha([
            'mobile' => '09120000003',
            'code' => $code,
        ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'کد تایید منقضی شده است. دوباره درخواست کنید.']);
    }

    public function test_wrong_otp_is_rejected(): void
    {
        $this->seedHashedOtp('09120000004', '12345');

        $this->postJson('/api/v1/auth/otp/verify', $this->withAuthCaptcha([
            'mobile' => '09120000004',
            'code' => '99999',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'کد تایید نادرست است.']);
    }

    public function test_otp_cannot_be_reused(): void
    {
        $code = '12345';
        $this->seedHashedOtp('09120000005', $code);

        $this->postJson('/api/v1/auth/otp/verify', $this->withAuthCaptcha([
            'mobile' => '09120000005',
            'code' => $code,
        ]))->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', $this->withAuthCaptcha([
            'mobile' => '09120000005',
            'code' => $code,
        ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_otp_send_is_rate_limited(): void
    {
        $payload = ['mobile' => '09120000006'];
        $this->postJson('/api/v1/auth/otp/send', $this->withAuthCaptcha($payload))->assertOk();
        $this->postJson('/api/v1/auth/otp/send', $this->withAuthCaptcha($payload))
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_password_login_and_logout(): void
    {
        $user = User::factory()->create([
            'username' => 'ali_user',
            'email' => 'ali@example.com',
            'password' => 'secret1234',
            'status' => 'active',
        ]);

        $login = $this->postJson('/api/v1/auth/login', $this->withAuthCaptcha([
            'login' => 'ali_user',
            'password' => 'secret1234',
        ]));

        $login->assertOk()->assertJsonPath('success', true);
        $token = $login->json('data.token');
        $this->assertNotEmpty($token);

        $this->withToken($token)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_register_with_mobile_requires_otp(): void
    {
        $res = $this->postJson('/api/v1/auth/register', $this->withAuthCaptcha([
            'name' => 'علی رضایی',
            'username' => 'ali_new',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'mobile' => '989120000007',
        ]));

        $res->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.needs_otp', true)
            ->assertJsonPath('data.token', null)
            ->assertJsonPath('data.mobile', '09120000007');

        $this->assertFalse((bool) User::query()->where('mobile', '09120000007')->value('is_verified'));
    }

    public function test_register_rejects_duplicate_username(): void
    {
        User::factory()->create(['username' => 'taken_user']);

        $this->postJson('/api/v1/auth/register', $this->withAuthCaptcha([
            'name' => 'کاربر',
            'username' => 'taken_user',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'email' => 'new@example.com',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_profile_update_and_secure_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['status' => 'active']);
        $token = $user->createToken('api')->plainTextToken;

        $img = imagecreatetruecolor(12, 12);
        ob_start();
        imagejpeg($img);
        imagedestroy($img);
        $jpeg = (string) ob_get_clean();

        $this->withToken($token)->putJson('/api/v1/auth/profile', [
            'name' => 'نام جدید',
            'photo' => 'data:image/jpeg;base64,'.base64_encode($jpeg),
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'نام جدید');

        $this->assertTrue(Storage::disk('public')->exists('avatars/'.$user->id.'.jpg'));

        $this->withToken($token)->putJson('/api/v1/auth/profile', [
            'photo' => 'data:image/jpeg;base64,'.base64_encode('<?php echo 1;'),
        ])->assertStatus(422);
    }

    public function test_inactive_user_cannot_login_or_use_token(): void
    {
        $user = User::factory()->create([
            'username' => 'blocked_u',
            'password' => 'secret1234',
            'status' => 'blocked',
        ]);

        $this->postJson('/api/v1/auth/login', $this->withAuthCaptcha([
            'login' => 'blocked_u',
            'password' => 'secret1234',
        ]))
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $user->update(['status' => 'active']);
        $token = $user->createToken('api')->plainTextToken;
        $user->update(['status' => 'blocked']);

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_inactive_user_cannot_receive_otp(): void
    {
        User::factory()->create([
            'mobile' => '09120000008',
            'status' => 'blocked',
        ]);

        $this->postJson('/api/v1/auth/otp/send', $this->withAuthCaptcha([
            'mobile' => '09120000008',
        ]))
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    protected function seedHashedOtp(string $mobile, string $code): User
    {
        return User::factory()->create([
            'mobile' => $mobile,
            'otp_code' => hash_hmac('sha256', $code, (string) config('app.key')),
            'otp_expires_at' => now()->addMinutes(2),
            'is_verified' => false,
            'status' => 'active',
        ]);
    }
}
