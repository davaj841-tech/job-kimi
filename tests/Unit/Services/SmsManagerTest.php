<?php

namespace Tests\Unit\Services;

use App\Jobs\SendSmsJob;
use App\Models\SmsLog;
use App\Services\Sms\SmsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SmsManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sms.enabled' => true,
            'sms.provider' => 'melipayamak',
            'sms.allow_log_fallback' => false,
            'sms.features.otp' => true,
            'sms.features.transactional' => true,
            'sms.logging.enabled' => true,
            'sms.melipayamak.username' => 'demo-user',
            'sms.melipayamak.password' => 'demo-pass',
            'sms.melipayamak.from' => '5000',
            'sms.melipayamak.api_url' => 'https://rest.payamak-panel.com/api/SendSMS',
            'sms.melipayamak.pattern_body_id' => null,
            'services.sms.gateway' => 'melipayamak',
        ]);
    }

    public function test_send_detailed_success_and_logs(): void
    {
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'Value' => '123456789012',
                'RetStatus' => 1,
                'StrRetStatus' => 'Ok',
            ], 200),
        ]);

        $result = app(SmsManager::class)->sendDetailed('09121234567', 'hello', 'transactional');

        $this->assertTrue($result->success);
        $this->assertSame('melipayamak', $result->provider);
        $this->assertDatabaseHas('sms_logs', [
            'recipient_masked' => '0912*****67',
            'status' => 'sent',
            'message_type' => 'transactional',
        ]);
    }

    public function test_sms_disabled_skips_without_sending(): void
    {
        config(['sms.enabled' => false]);
        Http::fake();

        $result = app(SmsManager::class)->sendDetailed('09121234567', 'hello');

        $this->assertTrue($result->skipped);
        Http::assertNothingSent();
    }

    public function test_invalid_mobile_fails(): void
    {
        Http::fake();

        $result = app(SmsManager::class)->sendDetailed('invalid', 'hello');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_mobile', $result->errorCode);
        Http::assertNothingSent();
    }

    public function test_otp_uses_pattern_when_configured(): void
    {
        config(['sms.melipayamak.pattern_body_id' => 99887]);

        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'Value' => '555666777',
                'RetStatus' => 1,
            ], 200),
        ]);

        $result = app(SmsManager::class)->sendOtpDetailed('09121234567', '12345');

        $this->assertTrue($result->success);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'BaseServiceNumber'));
    }

    public function test_provider_failure_is_logged(): void
    {
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response(['RetStatus' => 0], 200),
        ]);

        $result = app(SmsManager::class)->sendDetailed('09121234567', 'x');

        $this->assertFalse($result->success);
        $this->assertDatabaseHas('sms_logs', ['status' => 'failed']);
    }

    public function test_queue_dispatches_job(): void
    {
        Queue::fake();

        $ok = app(SmsManager::class)->queue('09121234567', 'queued message', 'transactional');

        $this->assertTrue($ok);
        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_health_reports_configuration(): void
    {
        Http::fake([
            'rest.payamak-panel.com/*/GetCredit' => Http::response(['RetStatus' => 1], 200),
        ]);

        $health = app(SmsManager::class)->health();

        $this->assertTrue($health['enabled']);
        $this->assertTrue($health['configured']);
        $this->assertSame('melipayamak', $health['provider']);
    }

    public function test_missing_credentials_fail_closed(): void
    {
        config([
            'sms.melipayamak.username' => null,
            'sms.melipayamak.password' => null,
            'sms.allow_log_fallback' => false,
        ]);

        Http::fake();

        $result = app(SmsManager::class)->sendDetailed('09121234567', 'x');

        $this->assertFalse($result->success);
        Http::assertNothingSent();
    }

    public function test_connection_timeout_fails(): void
    {
        Http::fake([
            'rest.payamak-panel.com/*' => function () {
                throw new ConnectionException('timeout');
            },
        ]);

        $result = app(SmsManager::class)->sendDetailed('09121234567', 'x');

        $this->assertFalse($result->success);
        $this->assertSame('connection', $result->errorCode);
    }
}
