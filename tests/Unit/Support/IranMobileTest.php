<?php

namespace Tests\Unit\Support;

use App\Support\IranMobile;
use PHPUnit\Framework\TestCase;

class IranMobileTest extends TestCase
{
    public function test_normalizes_common_iranian_formats(): void
    {
        $this->assertSame('09123456789', IranMobile::normalize('09123456789'));
        $this->assertSame('09123456789', IranMobile::normalize('+989123456789'));
        $this->assertSame('09123456789', IranMobile::normalize('989123456789'));
        $this->assertSame('09123456789', IranMobile::normalize('9123456789'));
        $this->assertSame('09123456789', IranMobile::normalize('00989123456789'));
        $this->assertSame('09123456789', IranMobile::normalize('۰۹۱۲۳۴۵۶۷۸۹'));
    }

    public function test_rejects_invalid_numbers(): void
    {
        $this->assertNull(IranMobile::normalize('02122334455'));
        $this->assertNull(IranMobile::normalize('0912'));
        $this->assertNull(IranMobile::normalize(''));
        $this->assertNull(IranMobile::normalize(null));
    }
}
