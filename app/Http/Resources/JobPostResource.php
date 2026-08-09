<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'seo_tag' => $this->seo_tag,
            'company_name' => $this->classification_name,
            'job_classification_id' => $this->job_classification_id,
            'classification_name' => $this->classification_name,
            'description' => $this->description,
            'province' => $this->province,
            'provinces' => $this->provinces ?? ($this->province ? [$this->province] : []),
            'city' => $this->city,
            'job_category' => $this->job_category,
            'registration_deadline' => $this->registration_deadline?->toIso8601String(),
            'exam_date' => $this->exam_date?->toIso8601String(),
            'registration_link' => $this->registration_link,
            'source_url' => $this->source_url,
            'attachment_path' => $this->attachment_path,
            'attachment_url' => $this->attachment_url,
            'attachments' => $this->when($this->relationLoaded('attachments'), function () {
                return $this->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'description' => $a->description,
                    'url' => $a->url,
                    'sort_order' => $a->sort_order,
                ])->values();
            }),
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'view_count' => $this->view_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'related_exams_count' => $this->related_exams_count ?? null,
            'related_pdfs_count' => $this->related_pdfs_count ?? null,
            'related_exams' => $this->when($this->relationLoaded('exams'), function () {
                return $this->exams->map(fn ($exam) => [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'slug' => $exam->slug,
                    'is_free' => $exam->is_free,
                    'price' => $exam->price,
                    'duration_minutes' => $exam->duration_minutes,
                    'total_questions' => $exam->total_questions,
                    'job_classification_id' => $exam->job_classification_id,
                ])->values();
            }),
            'related_pdfs' => $this->when($this->relationLoaded('pdfProducts'), function () {
                return $this->pdfProducts->map(fn ($pdf) => [
                    'id' => $pdf->id,
                    'title' => $pdf->title,
                    'price' => $pdf->price,
                    'thumbnail' => $pdf->thumbnail,
                    'job_classification_id' => $pdf->job_classification_id,
                ])->values();
            }),
            'related_pdf_products' => $this->when($this->relationLoaded('pdfProducts'), function () {
                return $this->pdfProducts->map(fn ($pdf) => [
                    'id' => $pdf->id,
                    'title' => $pdf->title,
                    'price' => $pdf->price,
                    'thumbnail' => $pdf->thumbnail,
                    'job_classification_id' => $pdf->job_classification_id,
                ])->values();
            }),
            'catalog_exams' => $this->when(isset($this->catalog_exams), $this->catalog_exams),
            'catalog_pdfs' => $this->when(isset($this->catalog_pdfs), $this->catalog_pdfs),
            'creator_name' => $this->when($this->relationLoaded('creator'), $this->creator?->name),
            'approver_name' => $this->when($this->relationLoaded('approver'), $this->approver?->name),
            'organization_name' => $this->company_name,
            'is_aggregated' => filled($this->job_source_id),
            'job_source_id' => $this->job_source_id,
            'external_id' => $this->external_id,
            'education' => $this->education,
            'field_of_study' => $this->field_of_study,
            'experience' => $this->experience,
            'employment_type' => $this->employment_type,
            'requirements' => $this->requirements,
            'registration_starts_at' => $this->registration_starts_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'job_source' => $this->when($this->relationLoaded('source') && $this->source, function () {
                return [
                    'id' => $this->source->id,
                    'name' => $this->source->name,
                    'domain' => $this->source->domain,
                    'slug' => $this->source->slug,
                    'reliability_level' => $this->source->reliability_level?->value,
                ];
            }),
        ];
    }
}
