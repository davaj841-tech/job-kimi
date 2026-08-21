<?php

namespace App\Http\Resources;

use App\Models\Exam;
use App\Models\JobPost;
use App\Models\JobPostAttachment;
use App\Models\PdfProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobPost
 *
 * @property-read JobPost $resource
 */
class JobPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'seo_tag' => $this->seo_tag,
            'company_name' => $this->classification_name,
            'job_classification_id' => $this->job_classification_id,
            'classification_name' => $this->classification_name,
            'description' => \App\Support\HtmlSanitizer::clean($this->description),
            'province' => $this->province,
            'provinces' => $this->provinces ?? ($this->province ? [$this->province] : []),
            'city' => $this->city,
            'job_category' => $this->job_category,
            'registration_deadline' => $this->registration_deadline?->toIso8601String(),
            'exam_date' => $this->exam_date?->toIso8601String(),
            'registration_link' => \App\Support\HtmlSanitizer::safeUrl($this->registration_link),
            'source_url' => \App\Support\HtmlSanitizer::safeUrl($this->source_url),
            'attachment_path' => $this->attachment_path,
            'attachment_url' => $this->attachment_url,
            'attachments' => $this->when($this->relationLoaded('attachments'), function (): array {
                return $this->attachments->map(fn (JobPostAttachment $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'description' => $a->description,
                    'url' => $a->url,
                    'sort_order' => $a->sort_order,
                ])->values()->all();
            }),
            'status' => $this->status,
            'auto_catalog' => (bool) ($this->auto_catalog ?? true),
            'exam_ids' => $this->exam_ids ?? [],
            'pdf_ids' => $this->pdf_ids ?? [],
            'is_featured' => $this->is_featured,
            'view_count' => $this->view_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'related_exams_count' => $this->related_exams_count ?? null,
            'related_pdfs_count' => $this->related_pdfs_count ?? null,
            'related_exams' => $this->when($this->relationLoaded('exams'), function (): array {
                return $this->exams->map(fn (Exam $exam) => [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'slug' => $exam->slug,
                    'is_free' => $exam->is_free,
                    'price' => $exam->price,
                    'duration_minutes' => $exam->duration_minutes,
                    'total_questions' => $exam->total_questions,
                    'job_classification_id' => $exam->job_classification_id,
                ])->values()->all();
            }),
            'related_pdfs' => $this->when($this->relationLoaded('pdfProducts'), function (): array {
                return $this->pdfProducts->map(fn (PdfProduct $pdf) => [
                    'id' => $pdf->id,
                    'title' => $pdf->title,
                    'price' => $pdf->price,
                    'thumbnail' => $pdf->thumbnail,
                    'job_classification_id' => $pdf->job_classification_id,
                ])->values()->all();
            }),
            'related_pdf_products' => $this->when($this->relationLoaded('pdfProducts'), function (): array {
                return $this->pdfProducts->map(fn (PdfProduct $pdf) => [
                    'id' => $pdf->id,
                    'title' => $pdf->title,
                    'price' => $pdf->price,
                    'thumbnail' => $pdf->thumbnail,
                    'job_classification_id' => $pdf->job_classification_id,
                ])->values()->all();
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
            'requirements' => \App\Support\HtmlSanitizer::clean($this->requirements),
            'registration_starts_at' => $this->registration_starts_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'job_source' => $this->when($this->relationLoaded('source') && $this->source, function () {
                return [
                    'id' => $this->source->id,
                    'name' => $this->source->name,
                    'domain' => $this->source->domain,
                    'slug' => $this->source->slug,
                    'reliability_level' => $this->source->reliability_level instanceof \BackedEnum
                        ? $this->source->reliability_level->value
                        : $this->source->reliability_level,
                ];
            }),
        ];

        $org = is_string($data['organization_name'] ?? null) ? $data['organization_name'] : null;
        $className = is_string($data['classification_name'] ?? null) ? $data['classification_name'] : null;
        $city = is_string($data['city'] ?? null) ? $data['city'] : null;
        $province = is_string($data['province'] ?? null) ? $data['province'] : null;
        $employmentType = is_string($data['employment_type'] ?? null) ? $data['employment_type'] : null;
        $deadlineIso = is_string($data['registration_deadline'] ?? null) ? $data['registration_deadline'] : null;

        $locationParts = array_values(array_unique(array_filter([$city, $province])));
        $typeLabel = $this->employmentTypeLabel($employmentType);

        $data['company'] = [
            'name' => $org ?: ($className ?: 'سازمان'),
            'logo' => null,
        ];
        $data['location'] = $locationParts !== [] ? implode('، ', $locationParts) : 'سراسر کشور';
        $data['type'] = $employmentType;
        $data['tags'] = array_values(array_unique(array_filter([
            is_string($data['job_category'] ?? null) ? $data['job_category'] : null,
            is_string($data['education'] ?? null) ? $data['education'] : null,
            is_string($data['experience'] ?? null) ? $data['experience'] : null,
            $typeLabel,
        ])));
        $data['deadline'] = $deadlineIso !== null ? substr($deadlineIso, 0, 10) : null;
        $data['published_at'] = $data['published_at'] ?? $data['created_at'];

        return $data;
    }

    protected function employmentTypeLabel(?string $type): ?string
    {
        return match ($type) {
            'full_time' => 'تمام‌وقت',
            'part_time' => 'پاره‌وقت',
            'remote' => 'دورکاری',
            'contract' => 'قراردادی',
            'internship' => 'کارآموزی',
            'military' => 'امریه',
            default => $type,
        };
    }
}
