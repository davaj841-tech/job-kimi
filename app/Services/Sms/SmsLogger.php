<?php

namespace App\Services\Sms;

use App\Models\SmsLog;
use App\Support\SmsMobileMask;

class SmsLogger
{
    public function record(SmsResult $result, string $mobile): void
    {
        if (! config('sms.logging.enabled', true)) {
            return;
        }

        try {
            SmsLog::query()->create([
                'recipient_masked' => SmsMobileMask::mask($mobile),
                'message_type' => $result->messageType,
                'provider' => $result->provider,
                'status' => $result->skipped ? 'skipped' : ($result->success ? 'sent' : 'failed'),
                'message_id' => $result->messageId,
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
                'duration_ms' => $result->durationMs > 0 ? $result->durationMs : null,
                'sent_at' => $result->success ? now() : null,
            ]);
        } catch (\Throwable) {
            // Never break SMS flow because logging failed.
        }
    }
}
