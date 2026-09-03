<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsManager;
use App\Support\IranMobile;
use App\Support\SmsMobileMask;
use Illuminate\Console\Command;

class SmsTestCommand extends Command
{
    protected $signature = 'sms:test
                            {mobile : Destination Iranian mobile (09xxxxxxxxx)}
                            {--message= : Plain message body (ignored for OTP/pattern)}
                            {--pattern : Force Melipayamak BaseServiceNumber / OTP path}
                            {--code=12345 : OTP/code variable for pattern or default message}
                            {--otp : Alias of --pattern (sends via OTP pipeline)}';

    protected $description = 'Send a real SMS to verify Melipayamak/Kavenegar integration (disabled in testing; secrets never printed)';

    public function handle(SmsManager $sms): int
    {
        if (app()->environment('testing')) {
            $this->error('sms:test is disabled in the testing environment.');

            return self::FAILURE;
        }

        $health = $sms->health();
        $this->table(['Check', 'Result'], [
            ['SMS enabled', $health['enabled'] ? 'yes' : 'no'],
            ['Provider', (string) $health['provider']],
            ['Credentials configured', $health['configured'] ? 'yes' : 'no'],
            ['From configured', ! empty($health['from_configured']) ? 'yes' : 'no'],
            ['OTP pattern configured', ! empty($health['pattern_configured']) ? 'yes' : 'no'],
            ['Log fallback', ! empty($health['allow_log_fallback']) ? 'yes' : 'no'],
            ['API reachable', $health['api_reachable'] === null ? 'n/a' : ($health['api_reachable'] ? 'yes' : 'no')],
        ]);

        if (! $health['enabled']) {
            $this->error('SMS is disabled (SMS_ENABLED=false or admin setting).');

            return self::FAILURE;
        }

        if (! $health['configured']) {
            $this->error('SMS credentials are not configured. Set MELIPAYAMAK_USERNAME/PASSWORD or Admin → Settings → SMS.');

            return self::FAILURE;
        }

        $mobile = IranMobile::normalize((string) $this->argument('mobile'));
        if ($mobile === null) {
            $this->error('شماره موبایل نامعتبر است. قالب: 09xxxxxxxxx');

            return self::FAILURE;
        }

        $this->warn('This command sends a REAL SMS and may incur provider charges.');
        $this->info('Mobile: '.SmsMobileMask::mask($mobile));

        $code = (string) $this->option('code');
        $useOtp = (bool) $this->option('pattern') || (bool) $this->option('otp');

        if ($useOtp || ($health['provider'] === 'melipayamak' && ! empty($health['pattern_configured']))) {
            $result = $sms->sendOtpDetailed($mobile, $code);
            $this->info('Mode: OTP (pattern when configured, else plain)');
        } else {
            $message = (string) ($this->option('message') ?: str_replace(
                '{code}',
                $code,
                (string) config('sms.otp.template', 'کد تایید جاب‌آزمون: {code}')
            ));
            $result = $sms->sendDetailed($mobile, $message, 'system');
            $this->info('Mode: plain SMS');
        }

        $this->table(['Field', 'Value'], [
            ['Success', $result->success ? 'yes' : 'no'],
            ['Provider', $result->provider],
            ['Status', (string) ($result->status ?? '-')],
            ['HTTP status', (string) ($result->httpStatus ?? '-')],
            ['Message ID', (string) ($result->messageId ?? '-')],
            ['Error code', (string) ($result->errorCode ?? '-')],
            ['Error message', (string) ($result->errorMessage ?? '-')],
            ['Provider response', $result->providerResponse ? json_encode($result->providerResponse, JSON_UNESCAPED_UNICODE) : '-'],
            ['Duration ms', (string) $result->durationMs],
        ]);

        if ($result->success) {
            $this->info('SMS Integration: PASS');

            return self::SUCCESS;
        }

        $this->error('SMS Integration: FAIL — '.($result->errorMessage ?? 'unknown error'));
        if (($result->errorCode ?? '') === 'InvalidBodyId') {
            $this->warn('Fix: set a valid sms_pattern_body_id (approved Melipayamak pattern) or clear it and set sms_from for plain OTP.');
        }
        if (($result->errorCode ?? '') === 'pattern_failed_no_from') {
            $this->warn('Fix: set Admin SMS → شماره خط ارسال (From), or fix the OTP pattern bodyId.');
        }

        return self::FAILURE;
    }
}
