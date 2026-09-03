<?php

namespace Tests\Unit\Support;

use App\Support\PaymentAmount;
use Tests\TestCase;

class PaymentAmountTest extends TestCase
{
    public function test_unit_and_currency_are_rial_irr(): void
    {
        $this->assertSame('rial', PaymentAmount::unit());
        $this->assertSame('IRR', PaymentAmount::currency());
    }

    public function test_wallet_charge_bounds(): void
    {
        config([
            'payment.min_wallet_charge' => 10000,
            'payment.max_wallet_charge' => 50_000_000,
        ]);

        $this->assertTrue(PaymentAmount::isValidWalletCharge(10000));
        $this->assertTrue(PaymentAmount::isValidWalletCharge(50_000_000));
        $this->assertFalse(PaymentAmount::isValidWalletCharge(9999));
        $this->assertFalse(PaymentAmount::isValidWalletCharge(50_000_001));
    }

    public function test_gateway_amount_minimum(): void
    {
        $this->assertTrue(PaymentAmount::isValidGatewayAmount(1000));
        $this->assertFalse(PaymentAmount::isValidGatewayAmount(999));
        $this->assertFalse(PaymentAmount::isValidGatewayAmount(0));
    }

    public function test_format_includes_rial_label(): void
    {
        $this->assertStringContainsString('ریال', PaymentAmount::format(50000));
    }
}
