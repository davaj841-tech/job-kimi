<?php

namespace App\Services\Sms;

final class SmsResult
{
    /**
     * @param  array<string, mixed>|null  $providerResponse
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $provider,
        public readonly string $messageType = 'transactional',
        public readonly ?string $messageId = null,
        public readonly ?string $status = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly int $durationMs = 0,
        public readonly bool $skipped = false,
        public readonly ?int $httpStatus = null,
        public readonly ?array $providerResponse = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $providerResponse
     */
    public static function success(
        string $provider,
        string $messageType = 'transactional',
        ?string $messageId = null,
        ?string $status = null,
        int $durationMs = 0,
        ?int $httpStatus = null,
        ?array $providerResponse = null,
    ): self {
        return new self(
            success: true,
            provider: $provider,
            messageType: $messageType,
            messageId: $messageId,
            status: $status ?? 'sent',
            durationMs: $durationMs,
            httpStatus: $httpStatus,
            providerResponse: $providerResponse,
        );
    }

    /**
     * @param  array<string, mixed>|null  $providerResponse
     */
    public static function failed(
        string $provider,
        string $messageType,
        ?string $errorCode,
        ?string $errorMessage,
        int $durationMs = 0,
        ?int $httpStatus = null,
        ?array $providerResponse = null,
    ): self {
        return new self(
            success: false,
            provider: $provider,
            messageType: $messageType,
            status: 'failed',
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            durationMs: $durationMs,
            httpStatus: $httpStatus,
            providerResponse: $providerResponse,
        );
    }

    public static function skipped(string $provider, string $messageType, string $reason): self
    {
        return new self(
            success: false,
            provider: $provider,
            messageType: $messageType,
            status: 'skipped',
            errorMessage: $reason,
            skipped: true,
        );
    }
}
