<?php

namespace App\Services\Payment;

use App\Models\PaymentGateway;
use App\Models\Setting;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGatewayInterface>> */
    protected array $drivers = [
        'zarinpal' => ZarinPalGateway::class,
        'nextpay' => NextPayGateway::class,
        'idpay' => IdPayGateway::class,
        'mellat' => MellatGateway::class,
        'shaparak' => ShaparakGateway::class,
    ];

    public function driver(?string $name = null): PaymentGatewayInterface
    {
        if (config('payment.fake') && ! app()->isProduction()) {
            return app(FakePaymentGateway::class);
        }

        $name = $name ?: $this->defaultName();

        if (! isset($this->drivers[$name])) {
            throw new InvalidArgumentException("درگاه پرداخت نامعتبر: {$name}");
        }

        return app($this->drivers[$name]);
    }

    public function defaultName(): string
    {
        $fromDb = PaymentGateway::query()->where('is_default', true)->where('is_active', true)->value('name');
        if ($fromDb) {
            return $fromDb;
        }

        return (string) Setting::get('payment_gateway', config('payment.default_gateway', 'zarinpal'));
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

        return $rows->map(fn (PaymentGateway $g) => [
            'name' => $g->name,
            'display_name' => $g->display_name,
            'is_default' => (bool) $g->is_default,
        ])->all();
    }

    public function isOnlineGateway(string $name): bool
    {
        return isset($this->drivers[$name]);
    }
}
