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

        $html = view('pdf.report-card', [
            'attempt' => $attempt,
            'exam' => $attempt->exam,
            'user' => $attempt->user,
            'sheet' => $payload['sheet'],
            'analysis' => $payload['analysis'],
            'fontRegular' => $this->persianFont->cssUrl($fonts['regular']),
            'fontBold' => $this->persianFont->cssUrl($fonts['bold']),
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
