<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserLoginSession;
use App\Services\Auth\LoginSessionService;
use App\Services\Auth\OtpAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class UserLoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_token_starts_login_session_and_logout_closes_it(): void
    {
        $user = User::factory()->create();
        $otp = app(OtpAuthService::class);

        $plain = $otp->issueToken($user, 'api');
        $this->assertNotEmpty($plain);

        $session = UserLoginSession::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($session);
        $this->assertNull($session->logged_out_at);
        $this->assertNotNull($session->token_id);

        $token = PersonalAccessToken::findToken($plain);
        $this->assertNotNull($token);
        $user->withAccessToken($token);

        $otp->logout($user);

        $session->refresh();
        $this->assertNotNull($session->logged_out_at);
        $this->assertNotNull($session->duration_seconds);
        $this->assertGreaterThanOrEqual(0, $session->duration_seconds);
    }

    public function test_activity_endpoint_returns_sessions_and_monthly(): void
    {
        $user = User::factory()->create();
        UserLoginSession::query()->create([
            'user_id' => $user->id,
            'token_id' => 1,
            'logged_in_at' => now()->subDays(2),
            'logged_out_at' => now()->subDays(2)->addHour(),
            'duration_seconds' => 3600,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'source' => 'api',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user/activity');
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertIsArray($response->json('data.sessions'));
        $this->assertNotEmpty($response->json('data.sessions'));
        $this->assertIsArray($response->json('data.monthly'));
    }

    public function test_monthly_summary_excludes_current_jalali_month(): void
    {
        $user = User::factory()->create();
        $service = app(LoginSessionService::class);

        UserLoginSession::query()->create([
            'user_id' => $user->id,
            'logged_in_at' => now(),
            'logged_out_at' => now()->addMinutes(30),
            'duration_seconds' => 1800,
            'source' => 'api',
        ]);

        $report = $service->reportForUser($user);
        $this->assertIsArray($report['monthly']);
        $nowJ = \Morilog\Jalali\Jalalian::now();
        foreach ($report['monthly'] as $row) {
            $this->assertFalse(
                (int) $row['year'] === $nowJ->getYear() && (int) $row['month'] === $nowJ->getMonth()
            );
        }
    }
}
