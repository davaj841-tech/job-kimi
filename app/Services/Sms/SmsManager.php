<?php

namespace App\Services\Sms;

use App\Jobs\SendSmsJob;
use App\Models\Setting;
use App\Services\Sms\Exceptions\SmsConfigurationException;
use App\Support\IranMobile;
use App\Support\SmsMobileMask;
use App\Support\SmsSecret;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsManager implements SmsServiceInterface
{
    public function __construct(protected SmsLogger $logger) {}

    public function gateway(): SmsGatewayInterface
    {
        return match ($this->resolveProviderName()) {
            'melipayamak' => app(MelipayamakProvider::class),
            default => app(KavenegarSmsGateway::class),
        };
    }

    public function sendSMS(string $mobile, string $message, string $messageType = 'transactional'): bool
    {
        return $this->sendDetailed($mobile, $message, $messageType)->success;
    }

    public function sendOtp(string $mobile, string $code): bool
    {
        return $this->sendOtpDetailed($mobile, $code)->success;
    }

    public function sendDetailed(string $mobile, string $message, string $messageType = 'transactional'): SmsResult
    {
        $mobile = $this->normalizeMobile($mobile);
        if ($mobile === null) {
            return SmsResult::failed('unknown', $messageType, 'invalid_mobile', 'شماره موبایل نامعتبر است.');
        }

        if (! $this->isEnabled()) {
            $result = $this->skipped($messageType, 'SMS disabled');
            $this->logger->record($result, $mobile);
            Log::info('SMS skipped (disabled)', ['mobile' => SmsMobileMask::mask($mobile), 'type' => $messageType]);

            return $result;
        }

        if ($messageType === 'otp' && ! $this->isOtpEnabled()) {
            $result = $this->skipped('otp', 'OTP SMS disabled');
            $this->logger->record($result, $mobile);

            return $result;
        }

        if ($messageType !== 'otp' && $messageType !== 'marketing' && ! $this->isTransactionalEnabled()) {
            $result = $this->skipped($messageType, 'Transactional SMS disabled');
            $this->logger->record($result, $mobile);

            return $result;
        }

        if ($messageType === 'marketing' && ! $this->isMarketingEnabled()) {
            $result = $this->skipped('marketing', 'Marketing SMS disabled');
            $this->logger->record($result, $mobile);

            return $result;
        }

        try {
            $gateway = $this->gateway();
            $result = $gateway->sendDetailed($mobile, $message, $messageType);
            $this->logger->record($result, $mobile);

            return $result;
        } catch (SmsConfigurationException $e) {
            $result = SmsResult::failed(
                $this->resolveProviderName(),
                $messageType,
                'configuration',
                $e->getMessage(),
            );
            $this->logger->record($result, $mobile);
            Log::error('SMS configuration error', [
                'mobile' => SmsMobileMask::mask($mobile),
                'type' => $messageType,
                'error' => $e->getMessage(),
            ]);

            return $result;
        } catch (Throwable $e) {
            $result = SmsResult::failed(
                $this->resolveProviderName(),
                $messageType,
                'exception',
                class_basename($e),
            );
            $this->logger->record($result, $mobile);
            Log::error('SMS send failed', [
                'mobile' => SmsMobileMask::mask($mobile),
                'type' => $messageType,
                'exception' => class_basename($e),
            ]);

            return $result;
        }
    }

    public function sendOtpDetailed(string $mobile, string $code): SmsResult
    {
        $mobile = $this->normalizeMobile($mobile);
        if ($mobile === null) {
            return SmsResult::failed('unknown', 'otp', 'invalid_mobile', 'شماره موبایل نامعتبر است.');
        }

        if (! $this->isEnabled() || ! $this->isOtpEnabled()) {
            $result = $this->skipped('otp', 'OTP SMS disabled');
            $this->logger->record($result, $mobile);

            return $result;
        }

        try {
            $gateway = $this->gateway();

            if ($gateway instanceof SupportsOtpPattern && $gateway->supportsOtpPattern()) {
                if ($gateway instanceof MeliPayamakSmsGateway) {
                    $result = $gateway->sendOtpPatternDetailed($mobile, $code);
                } else {
                    $ok = $gateway->sendOtpPattern($mobile, $code);
                    $result = $ok
                        ? SmsResult::success($gateway->name(), 'otp')
                        : SmsResult::failed($gateway->name(), 'otp', 'delivery_failed', 'OTP delivery failed');
                }
            } else {
                $result = $gateway->sendDetailed($mobile, $this->otpMessage($code), 'otp');
            }

            $this->logger->record($result, $mobile);

            return $result;
        } catch (Throwable $e) {
            $result = SmsResult::failed($this->resolveProviderName(), 'otp', 'exception', class_basename($e));
            $this->logger->record($result, $mobile);
            Log::error('SMS OTP send failed', [
                'mobile' => SmsMobileMask::mask($mobile),
                'exception' => class_basename($e),
            ]);

            return $result;
        }
    }

    public function queue(string $mobile, string $message, string $messageType = 'transactional'): bool
    {
        if (! config('sms.queue.enabled', true)) {
            return $this->sendSMS($mobile, $message, $messageType);
        }

        $mobile = $this->normalizeMobile($mobile);
        if ($mobile === null) {
            return false;
        }

        SendSmsJob::dispatch($mobile, $message, $messageType);

        if (config('sms.logging.enabled', true)) {
            try {
                \App\Models\SmsLog::query()->create([
                    'recipient_masked' => SmsMobileMask::mask($mobile),
                    'message_type' => $messageType,
                    'provider' => $this->resolveProviderName(),
                    'status' => 'queued',
                    'sent_at' => null,
                ]);
            } catch (Throwable) {
                // ignore
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        $provider = $this->resolveProviderName();
        $configured = $this->isProviderConfigured($provider);
        $reachable = null;

        if ($configured && $provider === 'melipayamak') {
            $reachable = $this->checkMelipayamakCredit();
        } elseif ($configured && $provider === 'kavenegar') {
            $reachable = $this->checkKavenegarReachable();
        }

        return [
            'enabled' => $this->isEnabled(),
            'provider' => $provider,
            'configured' => $configured,
            'api_reachable' => $reachable,
            'otp_enabled' => $this->isOtpEnabled(),
            'transactional_enabled' => $this->isTransactionalEnabled(),
            'marketing_enabled' => $this->isMarketingEnabled(),
            'queue_enabled' => (bool) config('sms.queue.enabled', true),
            'allow_log_fallback' => (bool) config('sms.allow_log_fallback', false),
            'from_configured' => $this->isFromConfigured($provider),
            'pattern_configured' => $this->isPatternConfigured($provider),
        ];
    }

    public function isEnabled(): bool
    {
        return $this->boolSetting('sms_enabled', 'sms.enabled', true);
    }

    public function isOtpEnabled(): bool
    {
        return $this->isEnabled() && $this->boolSetting('sms_otp_enabled', 'sms.features.otp', true);
    }

    public function isTransactionalEnabled(): bool
    {
        return $this->isEnabled() && $this->boolSetting('sms_transactional_enabled', 'sms.features.transactional', true);
    }

    public function isMarketingEnabled(): bool
    {
        return $this->isEnabled() && $this->boolSetting('sms_marketing_enabled', 'sms.features.marketing', false);
    }

    protected function resolveProviderName(): string
    {
        $fromSetting = Setting::getFilled('sms_gateway', null);
        if (is_string($fromSetting) && $fromSetting !== '') {
            return $fromSetting;
        }

        return (string) config('sms.provider', config('services.sms.gateway', 'melipayamak'));
    }

    protected function boolSetting(string $settingKey, string $configKey, bool $default): bool
    {
        $raw = Setting::getFilled($settingKey, null);
        if ($raw !== null && $raw !== '') {
            return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config($configKey, $default);
    }

    protected function normalizeMobile(string $mobile): ?string
    {
        return IranMobile::normalize($mobile);
    }

    protected function otpMessage(string $code): string
    {
        $template = (string) Setting::getFilled(
            'sms_otp_template',
            config('sms.otp.template', config('services.sms.otp_template', 'کد تایید جاب‌آزمون: {code}'))
        );

        $message = str_replace(['{code}', '{otp}', ':code', '%code%'], $code, $template);

        return $message !== '' ? $message : "کد تایید جاب‌آزمون: {$code}";
    }

    protected function skipped(string $messageType, string $reason): SmsResult
    {
        return SmsResult::skipped($this->resolveProviderName(), $messageType, $reason);
    }

    protected function isProviderConfigured(string $provider): bool
    {
        if ($provider === 'melipayamak') {
            $username = (string) Setting::getFilled(
                'sms_username',
                Setting::getFilled('sms_api_key', config('sms.melipayamak.username', config('services.melipayamak.username')))
            );
            $password = (string) Setting::getFilled('sms_password', config('sms.melipayamak.password', config('services.melipayamak.password')));

            return SmsSecret::isUsable($username) && SmsSecret::isUsable($password);
        }

        $apiKey = (string) Setting::getFilled('sms_api_key', config('sms.kavenegar.api_key', config('services.kavenegar.api_key')));

        return SmsSecret::isUsable($apiKey);
    }

    protected function isFromConfigured(string $provider): bool
    {
        if ($provider !== 'melipayamak') {
            return true;
        }

        $from = (string) Setting::getFilled('sms_from', config('sms.melipayamak.from', config('services.melipayamak.from', '')));

        return SmsSecret::isUsable($from);
    }

    protected function isPatternConfigured(string $provider): bool
    {
        if ($provider !== 'melipayamak') {
            return false;
        }

        $raw = Setting::getFilled('sms_pattern_body_id', config('sms.melipayamak.pattern_body_id'));

        return is_numeric($raw) && (int) $raw > 0;
    }

    protected function checkMelipayamakCredit(): ?bool
    {
        $username = (string) Setting::getFilled(
            'sms_username',
            Setting::getFilled('sms_api_key', config('sms.melipayamak.username'))
        );
        $password = (string) Setting::getFilled('sms_password', config('sms.melipayamak.password'));

        if (! SmsSecret::isUsable($username) || ! SmsSecret::isUsable($password)) {
            return false;
        }

        $base = rtrim((string) config('sms.melipayamak.api_url', config('services.melipayamak.api_url')), '/');
        $timeout = max(3, (int) config('sms.timeout', config('services.sms.timeout', 10)));

        try {
            $response = Http::asForm()
                ->timeout($timeout)
                ->connectTimeout(min(5, $timeout))
                ->post($base.'/GetCredit', [
                    'username' => $username,
                    'password' => $password,
                ]);

            if (! $response->successful()) {
                return false;
            }

            $body = $response->json();

            return is_array($body) && (int) ($body['RetStatus'] ?? 0) === 1;
        } catch (Throwable) {
            return false;
        }
    }

    protected function checkKavenegarReachable(): ?bool
    {
        $apiKey = (string) Setting::getFilled('sms_api_key', config('sms.kavenegar.api_key'));
        if (blank($apiKey)) {
            return false;
        }

        try {
            $response = Http::timeout(5)->get("https://api.kavenegar.com/v1/{$apiKey}/account/info.json");

            return $response->successful()
                && (int) data_get($response->json(), 'return.status', 0) === 200;
        } catch (Throwable) {
            return false;
        }
    }
}
