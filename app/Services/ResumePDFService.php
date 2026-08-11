<?php

namespace App\Services;

use App\Models\Resume;
use App\Services\Pdf\PersianPdfFont;
use App\Services\Pdf\PersianPdfText;
use Illuminate\Support\Facades\Storage;

class ResumePDFService
{
    public function __construct(
        protected PersianPdfFont $persianFont,
        protected PersianPdfText $persianText,
    ) {}

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
        $fonts = $this->persianFont->ensure();

        $html = view($this->viewName($resume->template_id), [
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
            'fontRegular' => $this->persianFont->cssUrl($fonts['regular']),
            'fontBold' => $this->persianFont->cssUrl($fonts['bold']),
            'fontPath' => $this->persianFont->cssUrl($fonts['regular']),
        ])->render();

        return $this->persianText->reshapeHtml($html);
    }

    public function generatePDF(Resume $resume): string
    {
        $html = $this->renderHtml($resume);

        $pdf = $this->persianFont->applyOptions(app('dompdf.wrapper'));
        $pdf->getOptions()->set('isRemoteEnabled', true);
        $pdf->getOptions()->set('chroot', base_path());
        $pdf->loadHTML($html)->setPaper('a4');

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
}
