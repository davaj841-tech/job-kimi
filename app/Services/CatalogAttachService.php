<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\JobClassification;
use App\Models\PdfProduct;

class CatalogAttachService
{
    /**
     * @param  array<int, mixed>  $examIds
     * @param  array<int, mixed>  $pdfIds
     * @return array{exams: array<int, mixed>, pdfs: array<int, mixed>}
     */
    public function resolve(?int $classId, bool $auto, array $examIds = [], array $pdfIds = []): array
    {
        $examIds = $this->intIds($examIds);
        $pdfIds = $this->intIds($pdfIds);

        $exams = collect();
        $pdfs = collect();

        if ($auto && $classId) {
            $classification = JobClassification::query()->find($classId);
            $classIds = $classification ? $classification->descendantAndSelfIds() : [$classId];
            $exams = Exam::query()
                ->whereIn('job_classification_id', $classIds)
                ->where('status', 'published')
                ->orderByDesc('id')
                ->limit(24)
                ->get();
            $pdfs = PdfProduct::query()
                ->whereIn('job_classification_id', $classIds)
                ->where('is_active', true)
                ->orderByDesc('id')
                ->limit(24)
                ->get();
        }

        if ($examIds !== []) {
            $extra = Exam::query()
                ->whereIn('id', $examIds)
                ->where('status', 'published')
                ->get();
            $exams = $exams->concat($extra)->unique('id');
        }

        if ($pdfIds !== []) {
            $extra = PdfProduct::query()
                ->whereIn('id', $pdfIds)
                ->where('is_active', true)
                ->get();
            $pdfs = $pdfs->concat($extra)->unique('id');
        }

        return [
            'exams' => $exams->values()->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'slug' => $exam->slug,
                'is_free' => (bool) $exam->is_free,
                'price' => $exam->price,
                'duration_minutes' => $exam->duration_minutes,
                'total_questions' => $exam->total_questions,
                'job_classification_id' => $exam->job_classification_id,
            ])->all(),
            'pdfs' => $pdfs->values()->map(fn (PdfProduct $pdf) => [
                'id' => $pdf->id,
                'title' => $pdf->title,
                'price' => $pdf->price,
                'thumbnail' => $pdf->thumbnail,
                'category' => $pdf->category,
                'job_classification_id' => $pdf->job_classification_id,
            ])->all(),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function intIds(mixed $ids): array
    {
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }
}
