<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Utf8Text;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class Utf8TextTest extends TestCase
{
    #[Test]
    public function it_sanitizes_invalid_utf8_bytes(): void
    {
        $broken = "متن معتبر".chr(0xDB)."و ادامه";
        $clean = Utf8Text::sanitize($broken);

        $this->assertTrue(mb_check_encoding($clean, 'UTF-8'));
        $this->assertStringNotContainsString(chr(0xDB), $clean);
    }

    #[Test]
    public function it_limits_without_unicode_ellipsis(): void
    {
        $long = str_repeat('آگهی استخدام بانک ', 40);
        $limited = Utf8Text::limit($long, 160);

        $this->assertLessThanOrEqual(160, mb_strlen($limited, 'UTF-8'));
        $this->assertStringEndsWith('...', $limited);
        $this->assertStringNotContainsString('…', $limited);
        $this->assertTrue(mb_check_encoding($limited, 'UTF-8'));
    }
}
