<?php

namespace App\Services\Payment;

use App\Models\PaymentGateway;
use App\Models\Setting;
use InvalidArgumentException;
use RuntimeException;

class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGatewayInterface>> */
    protected array $drivers = [
        'zarinpal' => ZarinPalGateway::class,
        'nextpay' => NextPayGateway::class,
        'idpay' => IdPayGateway::class,
        'mellat' => MellatGateway::class,
        'shaparak' => ShaparakGateway::class,
        'parsian' => ParsianGateway::class,
        'saman' => SamanGateway::class,
        'pasargad' => PasargadGateway::class,
        'ap' => ApGateway::class,
        'sadad' => SadadGateway::class,
    ];

    /** @return list<string> */
    public function registeredCodes(): array
    {
        return array_keys($this->drivers);
    }

    public function driver(?string $name = null): PaymentGatewayInterface
    {
        if (config('payment.fake') && ! app()->isProduction()) {
            return app(FakePaymentGateway::class);
        }

        $name = $name ?: $this->defaultName();

        if (! isset($this->drivers[$name])) {
            throw new InvalidArgumentException("درگاه پرداخت نامعتبر: {$name}");
        }

        /** @var PaymentGatewayInterface $driver */
        $driver = app($this->drivers[$name]);

        return $driver;
    }

    /**
     * Active + configured default, with safe fallbacks (never fatal if default disabled).
     */
    public function defaultName(): string
    {
        $fromDb = PaymentGateway::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->value('name');
        if (is_string($fromDb) && isset($this->drivers[$fromDb])) {
            return $fromDb;
        }

        $fromSetting = (string) Setting::get('payment_gateway', '');
        if ($fromSetting !== '' && isset($this->drivers[$fromSetting])) {
            $active = PaymentGateway::query()
                ->where('name', $fromSetting)
                ->where('is_active', true)
                ->exists();
            if ($active || ! PaymentGateway::query()->exists()) {
                return $fromSetting;
            }
        }

        $firstActive = PaymentGateway::query()->active()->value('name');
        if (is_string($firstActive) && isset($this->drivers[$firstActive])) {
            return $firstActive;
        }

        $configDefault = (string) config('payment.default_gateway', 'zarinpal');
        if (isset($this->drivers[$configDefault])) {
            return $configDefault;
        }

        return 'zarinpal';
    }

    /**
     * Resolve a payable gateway BEFORE creating a pending transaction.
     * May fall back to another active+configured gateway if preferred is unavailable.
     * Never called after a payment/authority already exists (avoids double payment).
     *
     * @throws RuntimeException when no online gateway can be used
     */
    public function assertPayable(?string $preferred = null): string
    {
        $candidates = [];

        if (is_string($preferred) && $preferred !== '' && isset($this->drivers[$preferred])) {
            $candidates[] = $preferred;
        }

        $default = $this->defaultName();
        if (! in_array($default, $candidates, true)) {
            $candidates[] = $default;
        }

        foreach (PaymentGateway::query()->active()->pluck('name') as $activeName) {
            if (is_string($activeName) && isset($this->drivers[$activeName]) && ! in_array($activeName, $candidates, true)) {
                $candidates[] = $activeName;
            }
        }

        $tableHasRows = PaymentGateway::query()->exists();

        foreach ($candidates as $name) {
            $row = PaymentGateway::query()->where('name', $name)->first();
            if ($row && ! $row->is_active) {
                continue;
            }
            if (! $row && $tableHasRows) {
                // Gateway code not seeded/activated in DB while other rows exist.
                continue;
            }

            $driver = $this->driver($name);
            if ($driver instanceof AbstractPaymentGateway && ! $driver->isConfigured()) {
                continue;
            }

            return $name;
        }

        throw new RuntimeException('هیچ درگاه پرداخت فعالی تنظیم نشده است.');
    }

    /**
     * @return list<array{name: string, display_name: string|null, is_default: bool}>
     */
    public function activeList(): array
    {
        $rows = PaymentGateway::query()->active()->get();

        if ($rows->isEmpty()) {
            return [[
                'name' => 'zarinpal',
                'display_name' => 'زرین‌پال',
                'is_default' => true,
            ]];
        }

        $default = $this->defaultName();

        return $rows->map(fn (PaymentGateway $g) => [
            'name' => $g->name,
            'display_name' => $g->display_name,
            'is_default' => $g->name === $default,
        ])->all();
    }

    public function isOnlineGateway(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    /**
     * Catalog metadata for admin UI (registered drivers).
     *
     * @return list<array{code: string, display_name: string, class: class-string}>
     */
    public function catalog(): array
    {
        $out = [];
        foreach ($this->drivers as $code => $class) {
            /** @var PaymentGatewayInterface $instance */
            $instance = app($class);
            $out[] = [
                'code' => $code,
                'display_name' => $instance->getDisplayName(),
                'class' => $class,
            ];
        }

        return $out;
    }
}
