<?php

namespace App\Services\Sms;

use App\Models\Setting;
use App\Support\SmsMobileMask;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class KavenegarSmsGateway implements SmsGatewayInterface
{
    public function name(): string
    {
        return 'kavenegar';
    }

    public function send(string $mobile, string $message): bool
    {
        return $this->sendDetailed($mobile, $message, 'transactional')->success;
    }

    public function sendDetailed(string $mobile, string $message, string $messageType = 'transactional'): SmsResult
    {
        $apiKey = (string) Setting::getFilled(
            'sms_api_key',
            config('sms.kavenegar.api_key', config('services.kavenegar.api_key'))
        );

        if (blank($apiKey)) {
            if (config('sms.allow_log_fallback', config('services.sms.allow_log_fallback'))) {
                Log::info('Kavenegar SMS skipped (no api key; log fallback)', [
                    'provider' => 'kavenegar',
                    'mobile' => SmsMobileMask::mask($mobile),
                ]);

                return SmsResult::success('kavenegar', $messageType, null, 'log_fallback');
            }

            Log::error('Kavenegar SMS aborted: missing API key', [
                'provider' => 'kavenegar',
                'mobile' => SmsMobileMask::mask($mobile),
            ]);

            return SmsResult::failed('kavenegar', $messageType, 'missing_credentials', 'SMS API key not configured');
        }

        $timeout = max(3, (int) config('sms.timeout', config('services.sms.timeout', 10)));
        $started = microtime(true);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(min(5, $timeout))
                ->get("https://api.kavenegar.com/v1/{$apiKey}/sms/send.json", [
                    'receptor' => $mobile,
                    'message' => $message,
                ]);
        } catch (ConnectionException $e) {
            $durationMs = (int) ((microtime(true) - $started) * 1000);
            Log::warning('Kavenegar SMS connection failed', [
                'provider' => 'kavenegar',
                'mobile' => SmsMobileMask::mask($mobile),
                'exception' => class_basename($e),
            ]);

            return SmsResult::failed('kavenegar', $messageType, 'connection', 'Connection failed', $durationMs);
        } catch (Throwable $e) {
            Log::error('Kavenegar SMS unexpected error', [
                'provider' => 'kavenegar',
                'mobile' => SmsMobileMask::mask($mobile),
                'exception' => class_basename($e),
            ]);

            return SmsResult::failed('kavenegar', $messageType, 'exception', class_basename($e));
        }

        $durationMs = (int) ((microtime(true) - $started) * 1000);
        $body = $response->json();
        $status = is_array($body) ? (int) data_get($body, 'return.status', 0) : 0;
        $ok = $response->successful() && $status === 200;
        $messageId = is_array($body) ? (string) data_get($body, 'entries.0.messageid', '') : null;

        if (! $ok) {
            Log::warning('Kavenegar SMS failed', [
                'provider' => 'kavenegar',
                'mobile' => SmsMobileMask::mask($mobile),
                'http_status' => $response->status(),
                'provider_status' => $status ?: null,
                'duration_ms' => $durationMs,
            ]);

            return SmsResult::failed('kavenegar', $messageType, (string) $status, 'Provider rejected request', $durationMs);
        }

        return SmsResult::success('kavenegar', $messageType, $messageId ?: null, 'sent', $durationMs);
    }
}
