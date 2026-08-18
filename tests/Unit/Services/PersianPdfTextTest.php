<?php

namespace Tests\Unit\Services;

use App\Services\Pdf\PersianPdfText;
use Tests\TestCase;

class PersianPdfTextTest extends TestCase
{
    public function test_it_converts_persian_to_presentation_forms(): void
    {
        $out = (new PersianPdfText)->reshape('سلام');

        $this->assertNotSame('سلام', $out);
        $this->assertNotSame('مالس', $out);
        $this->assertTrue(
            preg_match('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $out) === 1,
            'Expected Arabic presentation forms, got: '.$out
        );
    }

    public function test_it_leaves_latin_and_numbers_intact(): void
    {
        $this->assertSame('2026/08/11', (new PersianPdfText)->reshape('2026/08/11'));
        $this->assertSame('PDF', (new PersianPdfText)->reshape('PDF'));
        $reshaper = new PersianPdfText;
        $this->assertSame('۱۴۰۵/۰۵/۲۳ ۱۲:۳۰', $reshaper->reshape('۱۴۰۵/۰۵/۲۳ ۱۲:۳۰'));
        $this->assertSame('۶۷.۵٪', $reshaper->reshape('۶۷.۵٪'));
    }

    public function test_it_reshapes_html_text_nodes_only(): void
    {
        $html = '<h1>جاب‌آزمون</h1>';
        $out = (new PersianPdfText)->reshapeHtml($html);

        $this->assertStringStartsWith('<h1>', $out);
        $this->assertStringEndsWith('</h1>', $out);
        $this->assertStringNotContainsString('>جاب‌آزمون<', $out);
    }
}
