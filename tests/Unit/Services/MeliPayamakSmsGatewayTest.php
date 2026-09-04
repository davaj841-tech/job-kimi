<?php

namespace Tests\Unit\Services;

use App\Services\Sms\MeliPayamakSmsGateway;
use App\Services\Sms\SmsService;
use App\Support\SmsMobileMask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeliPayamakSmsGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sms.enabled' => true,
            'sms.allow_log_fallback' => false,
            'sms.provider' => 'melipayamak',
            'sms.melipayamak.username' => 'demo-user',
            'sms.melipayamak.password' => 'demo-pass',
            'sms.melipayamak.from' => '5000',
            'sms.melipayamak.api_url' => 'https://rest.payamak-panel.com/api/SendSMS',
            'sms.melipayamak.pattern_body_id' => null,
            'sms.melipayamak.pattern_text' => '{code}',
            'services.sms.gateway' => 'melipayamak',
            'services.sms.allow_log_fallback' => false,
            'services.sms.enabled' => true,
            'services.sms.timeout' => 5,
            'services.melipayamak.username' => 'demo-user',
            'services.melipayamak.password' => 'demo-pass',
            'services.melipayamak.from' => '5000',
            'services.melipayamak.api_url' => 'https://rest.payamak-panel.com/api/SendSMS',
            'services.melipayamak.pattern_body_id' => null,
            'services.melipayamak.pattern_text' => '{code}',
        ]);
    }

    public function test_plain_send_succeeds_when_ret_status_one(): void
    {
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'Value' => '123456789012',
                'RetStatus' => 1,
                'StrRetStatus' => 'Ok',
            ], 200),
        ]);

        $ok = (new MeliPayamakSmsGateway)->send('09121234567', 'hello');

        $this->assertTrue($ok);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/SendSMS')
            && $request['to'] === '09121234567'
            && $request['username'] === 'demo-user'
            && $request['from'] === '5000');
    }

    public function test_plain_send_fails_on_ret_status_zero(): void
    {
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'Value' => '0',
                'RetStatus' => 0,
                'StrRetStatus' => 'InvalidUser',
            ], 200),
        ]);

        $this->assertFalse((new MeliPayamakSmsGateway)->send('09121234567', 'hello'));
    }

    public function test_plain_send_fails_on_http_401(): void
    {
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response('Unauthorized', 401),
        ]);

        $this->assertFalse((new MeliPayamakSmsGateway)->send('09121234567', 'hello'));
    }

    public function test_plain_send_fails_on_timeout(): void
    {
        Http::fake([
            'rest.payamak-panel.com/*' => function () {
                throw new ConnectionException('timeout');
            },
        ]);

        $this->assertFalse((new MeliPayamakSmsGateway)->send('09121234567', 'hello'));
    }

    public function test_pattern_otp_uses_base_service_number(): void
    {
        config(['sms.melipayamak.pattern_body_id' => 99887, 'services.melipayamak.pattern_body_id' => 99887]);

        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'Value' => '555666777',
                'RetStatus' => 1,
                'StrRetStatus' => 'Ok',
            ], 200),
        ]);

        $gateway = new MeliPayamakSmsGateway;
        $this->assertTrue($gateway->supportsOtpPattern());
        $this->assertTrue($gateway->sendOtpPattern('09121234567', '12345'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'BaseServiceNumber')
            && $request['bodyId'] === '99887'
            && $request['text'] === '12345'
            && $request['to'] === '09121234567');
    }

    public function test_pattern_text_full_sentence_sends_code_only(): void
    {
        config([
            'sms.melipayamak.pattern_body_id' => 42,
            'sms.melipayamak.pattern_text' => 'کد تایید جاب‌آزمون: {code}',
            'services.melipayamak.pattern_body_id' => 42,
            'services.melipayamak.pattern_text' => 'کد تایید جاب‌آزمون: {code}',
        ]);

        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'Value' => '1',
                'RetStatus' => 1,
                'StrRetStatus' => 'Ok',
            ], 200),
        ]);

        $this->assertTrue((new MeliPayamakSmsGateway)->sendOtpPattern('09121234567', '99887'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'BaseServiceNumber')
            && $request['text'] === '99887');
    }

    public function test_pattern_failure_falls_back_to_plain_when_from_configured(): void
    {
        config([
            'sms.melipayamak.pattern_body_id' => 42,
            'services.melipayamak.pattern_body_id' => 42,
            'sms.melipayamak.from' => '5000',
        ]);

        Http::fake([
            'rest.payamak-panel.com/*/BaseServiceNumber' => Http::response([
                'Value' => '0',
                'RetStatus' => 0,
                'StrRetStatus' => 'InvalidBodyId',
            ], 200),
            'rest.payamak-panel.com/*/SendSMS' => Http::response([
                'Value' => '888999',
                'RetStatus' => 1,
                'StrRetStatus' => 'Ok',
            ], 200),
        ]);

        $this->assertTrue((new MeliPayamakSmsGateway)->sendOtpPattern('09121234567', '12345'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'BaseServiceNumber'));
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'SendSMS') || str_contains($request->url(), 'BaseServiceNumber')) {
                return false;
            }
            $data = $request->data();

            return ($data['from'] ?? null) === '5000'
                && str_contains((string) ($data['text'] ?? ''), '12345');
        });
    }

    public function test_pattern_failure_without_from_fails_closed(): void
    {
        config([
            'sms.melipayamak.pattern_body_id' => 42,
            'services.melipayamak.pattern_body_id' => 42,
            'sms.melipayamak.from' => '',
            'services.melipayamak.from' => '',
        ]);

        Http::fake([
            'rest.payamak-panel.com/*/BaseServiceNumber' => Http::response([
                'Value' => '0',
                'RetStatus' => 0,
                'StrRetStatus' => 'InvalidBodyId',
            ], 200),
        ]);

        $result = (new MeliPayamakSmsGateway)->sendOtpPatternDetailed('09121234567', '12345');

        $this->assertFalse($result->success);
        $this->assertSame('InvalidBodyId', $result->errorCode);
        $this->assertSame(200, $result->httpStatus);
        $this->assertIsArray($result->providerResponse);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/SendSMS')
            && ! str_contains($request->url(), 'BaseServiceNumber'));
    }

    public function test_masked_password_treated_as_missing_credentials(): void
    {
        config([
            'sms.allow_log_fallback' => false,
            'sms.melipayamak.username' => 'demo-user',
            'sms.melipayamak.password' => '********',
            'services.melipayamak.username' => 'demo-user',
            'services.melipayamak.password' => '********',
            'services.sms.allow_log_fallback' => false,
        ]);

        $result = (new MeliPayamakSmsGateway)->sendDetailed('09121234567', 'x', 'otp');

        $this->assertFalse($result->success);
        $this->assertSame('missing_credentials', $result->errorCode);
        Http::assertNothingSent();
    }

    public function test_failed_response_includes_http_and_provider_payload(): void
    {
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'Value' => '0',
                'RetStatus' => 0,
                'StrRetStatus' => 'InvalidUser',
            ], 200),
        ]);

        $result = (new MeliPayamakSmsGateway)->sendDetailed('09121234567', 'hello', 'otp');

        $this->assertFalse($result->success);
        $this->assertSame(200, $result->httpStatus);
        $this->assertSame('InvalidUser', $result->errorCode);
        $this->assertSame('InvalidUser', $result->providerResponse['StrRetStatus'] ?? null);
    }

    public function test_sms_service_prefers_pattern_for_otp(): void
    {
        config(['sms.melipayamak.pattern_body_id' => 111, 'services.melipayamak.pattern_body_id' => 111]);

        Http::fake([
            'rest.payamak-panel.com/*' => Http::response([
                'Value' => '999',
                'RetStatus' => 1,
                'StrRetStatus' => 'Ok',
            ], 200),
        ]);

        $this->assertTrue(app(SmsService::class)->sendOtp('09123334455', '54321'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'BaseServiceNumber'));
    }

    public function test_missing_credentials_fail_closed(): void
    {
        config([
            'sms.allow_log_fallback' => false,
            'sms.melipayamak.username' => null,
            'sms.melipayamak.password' => null,
            'services.melipayamak.username' => null,
            'services.melipayamak.password' => null,
            'services.sms.allow_log_fallback' => false,
        ]);

        $this->assertFalse((new MeliPayamakSmsGateway)->send('09121234567', 'x'));
    }

    public function test_mobile_mask_hides_middle_digits(): void
    {
        $this->assertSame('0912*****67', SmsMobileMask::mask('09121234567'));
    }

    public function test_malformed_response_fails(): void
    {
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response('not-json', 200),
        ]);

        $this->assertFalse((new MeliPayamakSmsGateway)->send('09121234567', 'x'));
    }

    public function test_http_429_fails(): void
    {
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response(['RetStatus' => 0], 429),
        ]);

        $this->assertFalse((new MeliPayamakSmsGateway)->send('09121234567', 'x'));
    }

    public function test_http_500_fails(): void
    {
        Http::fake([
            'rest.payamak-panel.com/*' => Http::response(['RetStatus' => 0], 500),
        ]);

        $this->assertFalse((new MeliPayamakSmsGateway)->send('09121234567', 'x'));
    }
}
