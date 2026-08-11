<?php

namespace Tests\Unit\Services;

use App\Services\Pdf\PersianPdfFont;
use Tests\TestCase;

class PersianPdfFontTest extends TestCase
{
    public function test_it_installs_real_vazirmatn_not_dejavu(): void
    {
        $service = app(PersianPdfFont::class);
        $fonts = $service->ensure();

        $this->assertFileExists($fonts['regular']);
        $this->assertGreaterThan(50_000, filesize($fonts['regular']));

        // DejaVuSans is typically ~700KB; Vazirmatn Regular ships ~120KB.
        $this->assertLessThan(400_000, filesize($fonts['regular']));

        $dejavu = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
        if (is_file($dejavu)) {
            $this->assertNotSame(md5_file($dejavu), md5_file($fonts['regular']));
        }
    }

    public function test_it_disables_font_subsetting_and_registers_vazirmatn(): void
    {
        $service = app(PersianPdfFont::class);
        $pdf = $service->applyOptions(app('dompdf.wrapper'));

        $this->assertFalse($pdf->getOptions()->getIsFontSubsettingEnabled());
        $this->assertFileExists(storage_path('fonts/installed-fonts.json'));
        $map = json_decode((string) file_get_contents(storage_path('fonts/installed-fonts.json')), true);
        $this->assertArrayHasKey('vazirmatn', $map);
    }
}
