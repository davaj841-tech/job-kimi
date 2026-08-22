<?php

namespace Tests\Feature;

use App\Services\HealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_returns_ok_when_all_checks_pass(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => [
                    'database' => ['status', 'response_time_ms'],
                    'redis' => ['status', 'response_time_ms'],
                    'queue' => ['status', 'pending_jobs'],
                    'storage' => ['status', 'writable'],
                ],
            ]);

        $this->assertSame('ok', $response->json('checks.database.status'));
        $this->assertSame('ok', $response->json('checks.storage.status'));
        $this->assertTrue($response->json('checks.storage.writable'));
        $this->assertIsInt($response->json('checks.database.response_time_ms'));
    }

    public function test_health_is_degraded_with_http_200_when_redis_fails(): void
    {
        $this->mock(HealthCheckService::class, function ($mock) {
            $mock->shouldReceive('run')->once()->andReturn([
                'status' => 'degraded',
                'timestamp' => now()->toIso8601String(),
                'http_status' => 200,
                'checks' => [
                    'database' => ['status' => 'ok', 'response_time_ms' => 3],
                    'redis' => ['status' => 'fail', 'response_time_ms' => 1, 'error' => 'connection refused'],
                    'queue' => ['status' => 'ok', 'pending_jobs' => 0, 'response_time_ms' => 1],
                    'storage' => ['status' => 'ok', 'writable' => true, 'response_time_ms' => 2],
                ],
            ]);
        });

        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.redis.status', 'fail')
            ->assertJsonPath('checks.database.status', 'ok');
    }

    public function test_health_is_degraded_with_http_200_when_queue_fails(): void
    {
        $this->mock(HealthCheckService::class, function ($mock) {
            $mock->shouldReceive('run')->once()->andReturn([
                'status' => 'degraded',
                'timestamp' => now()->toIso8601String(),
                'http_status' => 200,
                'checks' => [
                    'database' => ['status' => 'ok', 'response_time_ms' => 2],
                    'redis' => ['status' => 'ok', 'response_time_ms' => 1],
                    'queue' => ['status' => 'fail', 'pending_jobs' => 0, 'response_time_ms' => 1, 'error' => 'queue down'],
                    'storage' => ['status' => 'ok', 'writable' => true, 'response_time_ms' => 1],
                ],
            ]);
        });

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.queue.status', 'fail');
    }

    public function test_health_is_down_with_http_503_when_database_fails(): void
    {
        $this->mock(HealthCheckService::class, function ($mock) {
            $mock->shouldReceive('run')->once()->andReturn([
                'status' => 'down',
                'timestamp' => now()->toIso8601String(),
                'http_status' => 503,
                'checks' => [
                    'database' => ['status' => 'fail', 'response_time_ms' => 5, 'error' => 'SQLSTATE'],
                    'redis' => ['status' => 'ok', 'response_time_ms' => 1],
                    'queue' => ['status' => 'ok', 'pending_jobs' => 0, 'response_time_ms' => 1],
                    'storage' => ['status' => 'ok', 'writable' => true, 'response_time_ms' => 1],
                ],
            ]);
        });

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'down')
            ->assertJsonPath('checks.database.status', 'fail');
    }

    public function test_api_health_endpoint_shares_same_payload_shape(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => [
                    'database',
                    'redis',
                    'queue',
                    'storage',
                ],
            ]);
    }
}
