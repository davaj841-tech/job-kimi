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
        return 'pdf.resumes.template_1';
    }

    public function normalizeTemplateId(int|string|null $templateId): int
    {
        $id = (int) ($templateId ?: 1);
        if ($id < 1) {
            $id = 1;
        }
        if ($id > 10) {
            $id = (($id - 1) % 10) + 1;
        }

        return $id;
    }

    public function renderHtml(Resume $resume): string
    {
        $data = $resume->data ?? [];
        $fonts = $this->persianFont->ensure();
        $theme = $this->theme((int) ($resume->template_id ?: 1));

        $html = view($this->viewName($resume->template_id), [
            'resume' => $resume,
            'data' => $data,
            'personal' => $data['personal'] ?? [],
            'education' => array_values($data['education'] ?? []),
            'experience' => array_values($data['experience'] ?? []),
            'skills' => array_values($data['skills'] ?? []),
            'languages' => array_values($data['languages'] ?? []),
            'summary' => $data['summary'] ?? null,
            'targetJob' => $data['target_job'] ?? null,
            'photoSrc' => $this->photoSrc($resume),
            'photoPath' => $resume->photoAbsolutePath(),
            'accent' => $theme['accent'],
            'header' => $theme['header'],
            'sidebar' => $theme['sidebar'],
            'layout' => $theme['layout'],
            'fontFamily' => $theme['font'],
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

        try {
            $binary = $pdf->output();
        } catch (\Throwable $e) {
            report($e);
            throw new \RuntimeException('ساخت PDF رزومه ناموفق بود. فونت فارسی را بررسی کنید.', 0, $e);
        }

        $relative = 'resumes/'.$resume->user_id.'/resume_'.$resume->id.'_'.now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($relative, $binary);

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

    /**
     * @return array{accent: string, header: string, sidebar: string, layout: string, font: string}
     */
    protected function theme(int $id): array
    {
        $id = $this->normalizeTemplateId($id);
        $themes = [
            1 => ['accent' => '#1a365d', 'header' => '#1a365d', 'sidebar' => '#f8fafc', 'layout' => 'classic', 'font' => 'Vazirmatn'],
            2 => ['accent' => '#111827', 'header' => '#0f172a', 'sidebar' => '#0f172a', 'layout' => 'sidebar', 'font' => 'Vazirmatn'],
            3 => ['accent' => '#14532d', 'header' => '#14532d', 'sidebar' => '#ecfdf5', 'layout' => 'banner', 'font' => 'Vazirmatn'],
            4 => ['accent' => '#1d4ed8', 'header' => '#1e40af', 'sidebar' => '#eff6ff', 'layout' => 'split', 'font' => 'Vazirmatn'],
            5 => ['accent' => '#0f766e', 'header' => '#0d9488', 'sidebar' => '#f0fdfa', 'layout' => 'timeline', 'font' => 'Vazirmatn'],
            6 => ['accent' => '#4338ca', 'header' => '#4f46e5', 'sidebar' => '#eef2ff', 'layout' => 'cards', 'font' => 'Vazirmatn'],
            7 => ['accent' => '#be123c', 'header' => '#9f1239', 'sidebar' => '#fff1f2', 'layout' => 'magazine', 'font' => 'Vazirmatn'],
            8 => ['accent' => '#334155', 'header' => '#0f172a', 'sidebar' => '#f8fafc', 'layout' => 'compact', 'font' => 'Vazirmatn'],
            9 => ['accent' => '#b45309', 'header' => '#1c1917', 'sidebar' => '#fffbeb', 'layout' => 'elegant', 'font' => 'Vazirmatn'],
            10 => ['accent' => '#ea580c', 'header' => '#0f172a', 'sidebar' => '#fff7ed', 'layout' => 'bold', 'font' => 'Vazirmatn'],
        ];

        return $themes[$id] ?? $themes[1];
    }

    protected function photoSrc(Resume $resume): ?string
    {
        $photo = data_get($resume->data, 'personal.photo');
        $absolute = null;

        if (is_string($photo) && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $photo, $m)) {
            $raw = base64_decode(substr($photo, (int) strpos($photo, ',') + 1), true);
            if ($raw) {
                $ext = str_contains(strtolower($m[1]), 'png') ? 'png' : 'jpg';
                $rel = 'resumes/photos/'.$resume->id.'.'.$ext;
                Storage::disk('local')->put($rel, $raw);
                $absolute = Storage::disk('local')->path($rel);
            }
        }

        if (! $absolute) {
            $absolute = $resume->photoAbsolutePath();
        }

        if (! $absolute || ! is_file($absolute)) {
            return null;
        }

        return str_replace('\\', '/', $absolute);
    }
}
