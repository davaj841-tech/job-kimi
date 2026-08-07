<?php

namespace App\Services;

use App\Models\ExamAttempt;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportCardPDFService
{
    public function __construct(
        protected ExamService $examService
    ) {}

    public function renderHtml(ExamAttempt $attempt): string
    {
        $payload = $this->examService->buildAnswerSheet($attempt);
        $fontPath = $this->ensureVazirmatnFont();

        return view('pdf.report-card', [
            'attempt' => $attempt,
            'exam' => $attempt->exam,
            'user' => $attempt->user,
            'sheet' => $payload['sheet'],
            'analysis' => $payload['analysis'],
            'fontPath' => $fontPath,
            'generatedAt' => now(),
        ])->render();
    }

    public function download(ExamAttempt $attempt)
    {
        $html = $this->renderHtml($attempt);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setOptions([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'Vazirmatn',
                'isFontSubsettingEnabled' => true,
            ]);

        $filename = 'report-card-'.$attempt->exam_id.'-'.$attempt->id.'.pdf';

        return $pdf->download($filename);
    }

    public function stream(ExamAttempt $attempt)
    {
        $html = $this->renderHtml($attempt);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setOptions([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'Vazirmatn',
                'isFontSubsettingEnabled' => true,
            ]);

        $filename = 'report-card-'.$attempt->exam_id.'-'.$attempt->id.'.pdf';

        return $pdf->stream($filename);
    }

    protected function ensureVazirmatnFont(): string
    {
        $dir = storage_path('fonts');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fontFile = $dir.DIRECTORY_SEPARATOR.'Vazirmatn-Regular.ttf';

        if (! file_exists($fontFile)) {
            $fallback = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
            if (file_exists($fallback)) {
                copy($fallback, $fontFile);
            }
        }

        return $fontFile;
    }
}
