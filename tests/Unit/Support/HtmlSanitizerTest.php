<?php

namespace Tests\Unit\Support;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_strips_script_and_event_handlers(): void
    {
        $dirty = '<p onclick="alert(1)">سلام</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>';
        $clean = HtmlSanitizer::clean($dirty);

        $this->assertStringNotContainsString('<script', strtolower($clean));
        $this->assertStringNotContainsString('onclick', strtolower($clean));
        $this->assertStringNotContainsString('javascript:', strtolower($clean));
        $this->assertStringContainsString('سلام', $clean);
    }

    public function test_rejects_dangerous_urls(): void
    {
        $this->assertNull(HtmlSanitizer::safeUrl('javascript:alert(1)'));
        $this->assertNull(HtmlSanitizer::safeUrl('data:text/html,hi'));
        $this->assertSame('https://example.com', HtmlSanitizer::safeUrl('https://example.com'));
        $this->assertSame('/jobs/1', HtmlSanitizer::safeUrl('/jobs/1'));
    }
}
