<?php

namespace App\Services\Pdf;

use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use RuntimeException;

/**
 * Ensures real Vazirmatn TTF files are available for DomPDF (Persian-capable).
 * Never substitutes DejaVu under the Vazirmatn name — that produces unreadable PDFs.
 */
class PersianPdfFont
{
    /**
     * @return array{regular: string, bold: string, family: string}
     */
    public function ensure(): array
    {
        $dir = storage_path('fonts');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Cannot create storage/fonts directory.');
        }

        $regular = $dir.DIRECTORY_SEPARATOR.'Vazirmatn-Regular.ttf';
        $bold = $dir.DIRECTORY_SEPARATOR.'Vazirmatn-Bold.ttf';

        $this->syncFont(resource_path('fonts/Vazirmatn-Regular.ttf'), $regular);
        $this->syncFont(resource_path('fonts/Vazirmatn-Bold.ttf'), $bold);

        if (! is_file($regular)) {
            throw new RuntimeException(
                'فونت فارسی کارنامه یافت نشد. فایل resources/fonts/Vazirmatn-Regular.ttf را اضافه کنید.'
            );
        }

        if (! is_file($bold)) {
            $bold = $regular;
        }

        return [
            'regular' => $regular,
            'bold' => $bold,
            'family' => 'Vazirmatn',
        ];
    }

    /**
     * Absolute path suitable for DomPDF @font-face url().
     */
    public function cssUrl(string $absolutePath): string
    {
        return str_replace('\\', '/', $absolutePath);
    }

    public function applyOptions(DomPdfWrapper $pdf): DomPdfWrapper
    {
        $this->ensure();
        $this->writeInstalledMap();

        $options = $pdf->getOptions();
        $options->set('fontDir', storage_path('fonts'));
        $options->set('fontCache', storage_path('fonts'));
        $options->set('chroot', base_path());
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', false);
        $options->set('defaultFont', 'vazirmatn');
        $options->set('defaultMediaType', 'print');
        $options->set('isPhpEnabled', false);

        return $pdf;
    }

    protected function writeInstalledMap(): void
    {
        $path = storage_path('fonts'.DIRECTORY_SEPARATOR.'installed-fonts.json');
        $current = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            $current = is_array($decoded) ? $decoded : [];
        }

        $current['vazirmatn'] = [
            'normal' => 'Vazirmatn-Regular',
            'bold' => is_file(storage_path('fonts'.DIRECTORY_SEPARATOR.'Vazirmatn-Bold.ttf'))
                ? 'Vazirmatn-Bold'
                : 'Vazirmatn-Regular',
        ];

        file_put_contents(
            $path,
            json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    protected function syncFont(string $source, string $destination): void
    {
        if (! is_file($source)) {
            return;
        }

        // Replace missing or previously corrupted DejaVu-as-Vazirmatn copies.
        $needsCopy = ! is_file($destination)
            || filesize($destination) !== filesize($source)
            || md5_file($destination) !== md5_file($source);

        if ($needsCopy) {
            copy($source, $destination);
        }
    }
}
