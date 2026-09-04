<?php

namespace App\Services\Payment;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(protected GatewayCredentialsResolver $credentials) {}

    abstract public function getName(): string;

    abstract public function getDisplayName(): string;

    /**
     * Keys required before the gateway can be activated / used.
     *
     * @return list<string>
     */
    abstract public function requiredCredentialKeys(): array;

    public function isConfigured(): bool
    {
        foreach ($this->requiredCredentialKeys() as $key) {
            if (blank($this->credential($key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Lightweight config probe (no real charge). Override for HTTP ping when available.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'اطلاعات اتصال این درگاه کامل نشده است.',
            ];
        }

        return [
            'ok' => true,
            'message' => 'اتصال و تنظیمات معتبر است (اعتبارسنجی محلی credentials).',
        ];
    }

    protected function credential(string $logicalKey): string
    {
        $gateway = $this->getName();
        $settingKeys = [$gateway.'_'.$logicalKey, $logicalKey];
        $envPaths = [$logicalKey];
        $rowColumn = match ($logicalKey) {
            'merchant_id', 'pin', 'login_account', 'merchant_code', 'merchant_config_id' => 'merchant_id',
            'terminal_id' => in_array($gateway, ['mellat', 'shaparak', 'saman'], true) ? 'merchant_id' : null,
            'api_key', 'username' => 'api_key',
            default => null,
        };

        return $this->credentials->get($gateway, $settingKeys, $envPaths, $rowColumn);
    }
}
