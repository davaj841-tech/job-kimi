<?php

namespace App\Services;

use App\Models\Resume;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ResumePDFService
{
    public function viewName(int|string|null $templateId): string
    {
        $id = (int) ($templateId ?: 1);
        if (! in_array($id, [1, 2, 3], true)) {
            $id = 1;
        }

        return 'pdf.resumes.template_'.$id;
    }

    public function renderHtml(Resume $resume): string
    {
        $data = $resume->data ?? [];
        $fontPath = $this->ensureVazirmatnFont();

        return view($this->viewName($resume->template_id), [
            'resume' => $resume,
            'data' => $data,
            'personal' => $data['personal'] ?? [],
            'education' => $data['education'] ?? [],
            'experience' => $data['experience'] ?? [],
            'skills' => $data['skills'] ?? [],
            'languages' => $data['languages'] ?? [],
            'summary' => $data['summary'] ?? null,
            'targetJob' => $data['target_job'] ?? null,
            'photoPath' => $resume->photoAbsolutePath(),
            'fontPath' => $fontPath,
        ])->render();
    }

    public function generatePDF(Resume $resume): string
    {
        $html = $this->renderHtml($resume);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setOptions([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'Vazirmatn',
                'isFontSubsettingEnabled' => true,
            ]);

        $relative = 'resumes/'.$resume->user_id.'/resume_'.$resume->id.'_'.now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($relative, $pdf->output());

        $resume->update(['pdf_path' => $relative]);

        return url('/api/v1/resumes/'.$resume->id.'/pdf');
    }

    public function needsRegeneration(Resume $resume): bool
    {
        if (! $resume->pdf_path || ! Storage::disk('local')->exists($resume->pdf_path)) {
            return true;
        }

        $mtime = Storage::disk('local')->lastModified($resume->pdf_path);

        return $resume->updated_at && $resume->updated_at->getTimestamp() > $mtime;
    }

    public function absolutePath(Resume $resume): ?string
    {
        if (! $resume->pdf_path || ! Storage::disk('local')->exists($resume->pdf_path)) {
            return null;
        }

        return Storage::disk('local')->path($resume->pdf_path);
    }

    protected function ensureVazirmatnFont(): string
    {
        $dir = storage_path('fonts');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fontFile = $dir.DIRECTORY_SEPARATOR.'Vazirmatn-Regular.ttf';

        if (! file_exists($fontFile)) {
            // Fallback copy from DejaVu if download not available at runtime
            $fallback = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
            if (file_exists($fallback)) {
                copy($fallback, $fontFile);
            }
        }

        return $fontFile;
    }
}
