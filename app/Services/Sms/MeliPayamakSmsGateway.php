<?php

namespace App\Services\Sms;

use App\Models\Setting;
use App\Support\SmsMobileMask;
use App\Support\SmsSecret;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MeliPayamakSmsGateway implements SmsGatewayInterface, SupportsOtpPattern
{
    public function name(): string
    {
        return 'melipayamak';
    }

    public function send(string $mobile, string $message): bool
    {
        return $this->sendDetailed($mobile, $message, 'transactional')->success;
    }

    public function sendDetailed(string $mobile, string $message, string $messageType = 'transactional'): SmsResult
    {
        $credentials = $this->credentials();
        if ($credentials === null) {
            return $this->missingCredentialsResult($mobile, $messageType);
        }

        [$username, $password, $from] = $credentials;

        return $this->request(
            endpoint: 'SendSMS',
            payload: [
                'username' => $username,
                'password' => $password,
                'to' => $mobile,
                'from' => $from,
                'text' => $message,
                'isFlash' => 'false',
            ],
            mobile: $mobile,
            mode: 'plain',
            messageType: $messageType,
        );
    }

    public function supportsOtpPattern(): bool
    {
        return filled($this->patternBodyId());
    }

    public function sendOtpPattern(string $mobile, string $code): bool
    {
        return $this->sendOtpPatternDetailed($mobile, $code)->success;
    }

    public function sendOtpPatternDetailed(string $mobile, string $code): SmsResult
    {
        $credentials = $this->credentials();
        if ($credentials === null) {
            return $this->missingCredentialsResult($mobile, 'otp');
        }

        $bodyId = $this->patternBodyId();
        if ($bodyId === null) {
            return $this->sendDetailed($mobile, $this->fallbackOtpMessage($code), 'otp');
        }

        [$username, $password, $from] = $credentials;

        $patternResult = $this->request(
            endpoint: 'BaseServiceNumber',
            payload: [
                'username' => $username,
                'password' => $password,
                'to' => $mobile,
                // Melipayamak shared patterns expect ARGUMENT values (e.g. "12345"), not a free-form SMS body.
                'text' => $this->patternArguments($code),
                'bodyId' => (string) $bodyId,
            ],
            mobile: $mobile,
            mode: 'pattern',
            messageType: 'otp',
        );

        if ($patternResult->success) {
            return $patternResult;
        }

        // Pattern rejection → fall back to plain SendSMS (requires sender line for most accounts).
        Log::warning('MeliPayamak OTP pattern failed; attempting plain SendSMS fallback', [
            'provider' => 'melipayamak',
            'mobile' => SmsMobileMask::mask($mobile),
            'http_status' => $patternResult->httpStatus,
            'error_code' => $patternResult->errorCode,
            'error_message' => $patternResult->errorMessage,
            'provider_response' => $patternResult->providerResponse,
            'from_configured' => filled($from),
        ]);

        if (! filled($from)) {
            return SmsResult::failed(
                'melipayamak',
                'otp',
                $patternResult->errorCode ?: 'pattern_failed_no_from',
                'پترن OTP ناموفق است و شماره خط ارسال (sms_from) تنظیم نشده؛ fallback ممکن نیست.',
                $patternResult->durationMs,
                $patternResult->httpStatus,
                $patternResult->providerResponse,
            );
        }

        return $this->sendDetailed($mobile, $this->fallbackOtpMessage($code), 'otp');
    }

    /**
     * @return array{0: string, 1: string, 2: string}|null
     */
    protected function credentials(): ?array
    {
        $username = (string) Setting::getFilled(
            'sms_username',
            Setting::getFilled('sms_api_key', $this->config('username'))
        );
        $password = (string) Setting::getFilled('sms_password', $this->config('password'));
        $from = (string) Setting::getFilled('sms_from', $this->config('from', ''));

        if (! SmsSecret::isUsable($username) || ! SmsSecret::isUsable($password)) {
            return null;
        }

        // Ignore masked "from" placeholders; treat as empty.
        if (SmsSecret::isPlaceholder($from)) {
            $from = '';
        }

        return [$username, $password, $from];
    }

    protected function patternBodyId(): ?int
    {
        $raw = Setting::getFilled('sms_pattern_body_id', $this->config('pattern_body_id'));
        if ($raw === null || $raw === '') {
            return null;
        }

        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }

    /**
     * Values for Melipayamak BaseServiceNumber `text` (semicolon-separated args).
     * Never send a full Persian sentence here — the approved pattern already contains the wording.
     */
    protected function patternArguments(string $code): string
    {
        $template = trim((string) Setting::getFilled(
            'sms_pattern_text',
            $this->config('pattern_text', '{code}')
        ));

        if ($template === '' || in_array($template, ['{code}', '{otp}', ':code', '%code%'], true)) {
            return $code;
        }

        $rendered = str_replace(
            ['{code}', '{otp}', ':code', '%code%'],
            $code,
            $template
        );

        // Admin sometimes pastes a full SMS body into pattern_text; Melipayamak rejects that for patterns.
        if ($rendered === '' || preg_match('/\s/u', $rendered) || mb_strlen($rendered) > 64) {
            return $code;
        }

        return $rendered;
    }

    protected function fallbackOtpMessage(string $code): string
    {
        $template = (string) Setting::getFilled(
            'sms_otp_template',
            config('sms.otp.template', config('services.sms.otp_template', 'کد تایید جاب‌آزمون: {code}'))
        );

        return str_replace(['{code}', '{otp}', ':code', '%code%'], $code, $template);
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config("sms.melipayamak.{$key}", config("services.melipayamak.{$key}", $default));
    }

    /**
     * @param  array<string, string>  $payload
     */
    protected function request(string $endpoint, array $payload, string $mobile, string $mode, string $messageType): SmsResult
    {
        $base = rtrim((string) $this->config('api_url', 'https://rest.payamak-panel.com/api/SendSMS'), '/');
        $url = $base.'/'.$endpoint;
        $timeout = max(3, (int) config('sms.timeout', config('services.sms.timeout', 10)));
        $started = microtime(true);

        try {
            $response = Http::asForm()
                ->timeout($timeout)
                ->connectTimeout(min(5, $timeout))
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            $durationMs = (int) ((microtime(true) - $started) * 1000);
            Log::warning('MeliPayamak SMS connection failed', [
                'provider' => 'melipayamak',
                'mode' => $mode,
                'endpoint' => $endpoint,
                'mobile' => SmsMobileMask::mask($mobile),
                'exception' => class_basename($e),
                'message' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            return SmsResult::failed('melipayamak', $messageType, 'connection', $e->getMessage() ?: 'Connection failed', $durationMs);
        } catch (Throwable $e) {
            Log::error('MeliPayamak SMS unexpected error', [
                'provider' => 'melipayamak',
                'mode' => $mode,
                'endpoint' => $endpoint,
                'mobile' => SmsMobileMask::mask($mobile),
                'exception' => class_basename($e),
                'message' => $e->getMessage(),
            ]);

            return SmsResult::failed('melipayamak', $messageType, 'exception', class_basename($e).': '.$e->getMessage());
        }

        return $this->mapResponse($response, $mobile, $mode, $messageType, $endpoint, $started);
    }

    protected function mapResponse(
        Response $response,
        string $mobile,
        string $mode,
        string $messageType,
        string $endpoint,
        float $started,
    ): SmsResult {
        $durationMs = (int) ((microtime(true) - $started) * 1000);
        $httpStatus = $response->status();
        $body = $response->json();
        $safeBody = $this->safeProviderResponse($body, $response->body());
        $ok = $response->successful() && $this->isSuccessfulPayload($body);
        $messageId = is_array($body) ? $this->extractMessageId($body) : null;
        $errorCode = is_array($body)
            ? (string) ($body['StrRetStatus'] ?? $body['RetStatus'] ?? '')
            : (string) $httpStatus;

        if (! $ok) {
            $errorMessage = $this->humanizeProviderError($safeBody, $httpStatus);
            Log::warning('MeliPayamak SMS failed', [
                'provider' => 'melipayamak',
                'mode' => $mode,
                'endpoint' => $endpoint,
                'mobile' => SmsMobileMask::mask($mobile),
                'http_status' => $httpStatus,
                'ret_status' => is_array($body) ? ($body['RetStatus'] ?? null) : null,
                'str_ret_status' => is_array($body) ? ($body['StrRetStatus'] ?? null) : null,
                'value' => is_array($body) ? ($body['Value'] ?? null) : null,
                'provider_response' => $safeBody,
                'duration_ms' => $durationMs,
            ]);

            return SmsResult::failed(
                'melipayamak',
                $messageType,
                $errorCode !== '' ? $errorCode : 'delivery_failed',
                $errorMessage,
                $durationMs,
                $httpStatus,
                $safeBody,
            );
        }

        Log::info('MeliPayamak SMS sent', [
            'provider' => 'melipayamak',
            'mode' => $mode,
            'endpoint' => $endpoint,
            'mobile' => SmsMobileMask::mask($mobile),
            'http_status' => $httpStatus,
            'ret_status' => is_array($body) ? ($body['RetStatus'] ?? 1) : 1,
            'str_ret_status' => is_array($body) ? ($body['StrRetStatus'] ?? null) : null,
            'message_id' => $messageId,
            'duration_ms' => $durationMs,
        ]);

        return SmsResult::success('melipayamak', $messageType, $messageId, 'sent', $durationMs, $httpStatus, $safeBody);
    }

    /**
     * @return array<string, mixed>
     */
    protected function safeProviderResponse(mixed $body, string $raw): array
    {
        if (is_array($body)) {
            return [
                'RetStatus' => $body['RetStatus'] ?? null,
                'StrRetStatus' => $body['StrRetStatus'] ?? null,
                'Value' => $body['Value'] ?? null,
            ];
        }

        $snippet = trim(mb_substr($raw, 0, 200));

        return ['raw' => $snippet !== '' ? $snippet : null];
    }

    /**
     * @param  array<string, mixed>  $safeBody
     */
    protected function humanizeProviderError(array $safeBody, int $httpStatus): string
    {
        $str = (string) ($safeBody['StrRetStatus'] ?? '');
        $ret = $safeBody['RetStatus'] ?? null;

        return match (true) {
            $httpStatus === 401, $str === 'InvalidUser' => 'نام کاربری یا رمز وب‌سرویس ملی پیامک نامعتبر است.',
            $str === 'InvalidBodyId' => 'شناسه پترن OTP (bodyId) نامعتبر یا تأییدنشده است.',
            $str === 'InvalidData' => 'پارامترهای ارسال SMS نامعتبر است (شماره/متن/خط).',
            is_numeric($ret) && (int) $ret === 0 && $str !== '' => 'Provider rejected request: '.$str,
            $httpStatus >= 500 => 'سرور ملی پیامک در دسترس نیست (HTTP '.$httpStatus.').',
            $httpStatus >= 400 => 'درخواست SMS رد شد (HTTP '.$httpStatus.').',
            default => 'Provider rejected request'.($str !== '' ? ': '.$str : ''),
        };
    }

    protected function extractMessageId(array $body): ?string
    {
        $value = $body['Value'] ?? null;
        if ($value === null) {
            return null;
        }

        $str = (string) $value;

        return $str !== '' && (int) $value > 0 ? $str : null;
    }

    protected function isSuccessfulPayload(mixed $body): bool
    {
        if (! is_array($body)) {
            return false;
        }

        $retStatus = $body['RetStatus'] ?? null;
        if ($retStatus !== null && (int) $retStatus === 1) {
            return true;
        }

        $value = $body['Value'] ?? null;
        if (is_numeric($value) && (float) $value > 0 && (int) $retStatus !== 0) {
            if ((int) $value < 0) {
                return false;
            }

            return (int) $retStatus === 1 || ($retStatus === null && strlen((string) $value) >= 5);
        }

        return false;
    }

    protected function missingCredentialsResult(string $mobile, string $messageType): SmsResult
    {
        if (config('sms.allow_log_fallback', config('services.sms.allow_log_fallback'))) {
            Log::info('MeliPayamak SMS skipped (no credentials; log fallback)', [
                'provider' => 'melipayamak',
                'mobile' => SmsMobileMask::mask($mobile),
            ]);

            return SmsResult::success('melipayamak', $messageType, null, 'log_fallback');
        }

        Log::error('MeliPayamak SMS aborted: missing credentials', [
            'provider' => 'melipayamak',
            'mobile' => SmsMobileMask::mask($mobile),
        ]);

        return SmsResult::failed('melipayamak', $messageType, 'missing_credentials', 'SMS credentials not configured');
    }
}
