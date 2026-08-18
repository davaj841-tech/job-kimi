<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Services\Pdf\PersianPdfFont;
use App\Services\Pdf\PersianPdfText;
use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;

class ReportCardPDFService
{
    public function __construct(
        protected ExamService $examService,
        protected PersianPdfFont $persianFont,
        protected PersianPdfText $persianText,
    ) {}

    public function renderHtml(ExamAttempt $attempt): string
    {
        $payload = $this->examService->buildAnswerSheet($attempt);
        $fonts = $this->persianFont->ensure();
        $finishedAt = $attempt->finished_at ?? $attempt->updated_at ?? now();
        $startedAt = $attempt->started_at;

        $html = view('pdf.report-card', [
            'attempt' => $attempt,
            'exam' => $attempt->exam,
            'user' => $attempt->user,
            'sheet' => $payload['sheet'],
            'analysis' => $payload['analysis'],
            'siteName' => (string) (\App\Models\Setting::getFilled('site_name') ?: 'جاب‌آزمون'),
            'logoDataUri' => self::logoDataUri(),
            'fontRegular' => $this->persianFont->cssUrl($fonts['regular']),
            'fontBold' => $this->persianFont->cssUrl($fonts['bold']),
            'startedAtFa' => $startedAt
                ? self::fa(Jalalian::fromCarbon(Carbon::parse($startedAt))->format('Y/m/d H:i'))
                : '—',
            'finishedAtFa' => self::fa(Jalalian::fromCarbon(Carbon::parse($finishedAt))->format('Y/m/d H:i')),
            'generatedAtFa' => self::fa(Jalalian::now()->format('Y/m/d H:i')),
        ])->render();

        return $this->persianText->reshapeHtml($html);
    }

    public function download(ExamAttempt $attempt)
    {
        $html = $this->renderHtml($attempt);
        $pdf = $this->persianFont->applyOptions(app('dompdf.wrapper'));
        $pdf->loadHTML($html)->setPaper('a4');
        $filename = 'report-card-'.$attempt->exam_id.'-'.$attempt->id.'.pdf';

        return $pdf->download($filename);
    }

    public function stream(ExamAttempt $attempt)
    {
        $html = $this->renderHtml($attempt);
        $pdf = $this->persianFont->applyOptions(app('dompdf.wrapper'));
        $pdf->loadHTML($html)->setPaper('a4');
        $filename = 'report-card-'.$attempt->exam_id.'-'.$attempt->id.'.pdf';

        return $pdf->stream($filename);
    }

    public static function fa(mixed $value): string
    {
        return strtr((string) $value, [
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹',
        ]);
    }

    public static function faPct(mixed $value): string
    {
        $n = (float) $value;
        $formatted = abs($n - round($n)) < 0.05
            ? (string) (int) round($n)
            : number_format($n, 1, '.', '');

        return self::fa($formatted).'٪';
    }

    public static function logoDataUri(): ?string
    {
        $raw = (string) (\App\Models\Setting::getFilled('site_logo') ?: '');
        if ($raw === '') {
            return null;
        }

        $url = \App\Support\PublicAsset::url($raw);
        $full = null;
        if (str_starts_with($url, '/storage/')) {
            $full = storage_path('app/public/'.ltrim(substr($url, 9), '/'));
        } elseif (str_starts_with($url, '/')) {
            $full = public_path(ltrim($url, '/'));
        }

        if (! $full || ! is_file($full)) {
            return null;
        }

        $mime = mime_content_type($full) ?: 'image/png';
        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($full));
    }

    public static function optionLetter(?string $letter): string
    {
        return match (strtolower(trim((string) $letter))) {
            'a' => 'الف',
            'b' => 'ب',
            'c' => 'ج',
            'd' => 'د',
            '' => '—',
            default => strtoupper((string) $letter),
        };
    }
}
