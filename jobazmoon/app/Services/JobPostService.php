<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\JobClassification;
use App\Models\JobPost;
use App\Models\JobPostAttachment;
use App\Models\PdfProduct;
use App\Repositories\JobPostRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JobPostService
{
    public function __construct(
        protected JobPostRepository $jobPostRepository
    ) {}

    public function getPublicList(array $filters): LengthAwarePaginator
    {
        return $this->jobPostRepository->getApproved($filters);
    }

    public function getPendingForReview(): Collection
    {
        return $this->jobPostRepository->getPending();
    }

    public function approve(int $id, int $adminId): JobPost
    {
        $jobPost = $this->jobPostRepository->findById($id);

        if (! $jobPost) {
            throw new \RuntimeException('آگهی یافت نشد.');
        }

        $jobPost->update([
            'status' => 'approved',
            'approved_by' => $adminId,
        ]);

        $jobPost = $jobPost->fresh(['creator', 'approver', 'exams', 'pdfProducts', 'classification', 'attachments']);

        event(new \App\Events\JobPostApproved($jobPost));

        return $jobPost;
    }

    public function reject(int $id, ?string $reason = null): JobPost
    {
        $jobPost = $this->jobPostRepository->findById($id);

        if (! $jobPost) {
            throw new \RuntimeException('آگهی یافت نشد.');
        }

        $jobPost->update(['status' => 'rejected']);
        unset($reason);

        return $jobPost->fresh(['creator', 'approver', 'classification', 'attachments']);
    }

    public function incrementViews(int $id): void
    {
        JobPost::query()->whereKey($id)->increment('view_count');
    }

    public function create(array $data, array $newAttachments = []): JobPost
    {
        return DB::transaction(function () use ($data, $newAttachments) {
            $data = $this->normalizePayload($data);
            $legacyFile = $data['_legacy_attachment'] ?? null;
            unset($data['_legacy_attachment'], $data['attachment'], $data['attachments'], $data['attachment_titles'], $data['attachment_descriptions'], $data['remove_attachment_ids']);

            if ($legacyFile instanceof UploadedFile) {
                $data['attachment_path'] = $legacyFile->store('job-attachments', 'public');
            }

            $jobPost = JobPost::query()->create($data);
            $this->storeAttachments($jobPost, $newAttachments);

            return $jobPost->load(['classification', 'creator', 'approver', 'attachments']);
        });
    }

    public function update(JobPost $jobPost, array $data, array $newAttachments = []): JobPost
    {
        return DB::transaction(function () use ($jobPost, $data, $newAttachments) {
            $removeIds = $data['remove_attachment_ids'] ?? [];
            $data = $this->normalizePayload($data);
            $legacyFile = $data['_legacy_attachment'] ?? null;
            unset($data['_legacy_attachment'], $data['attachment'], $data['attachments'], $data['attachment_titles'], $data['attachment_descriptions'], $data['remove_attachment_ids']);

            if ($legacyFile instanceof UploadedFile) {
                if ($jobPost->attachment_path) {
                    Storage::disk('public')->delete($jobPost->attachment_path);
                }
                $data['attachment_path'] = $legacyFile->store('job-attachments', 'public');
            }

            $jobPost->update($data);

            if (is_array($removeIds) && count($removeIds)) {
                $this->removeAttachments($jobPost, $removeIds);
            }

            $this->storeAttachments($jobPost, $newAttachments);

            return $jobPost->fresh(['creator', 'approver', 'exams', 'pdfProducts', 'classification', 'attachments']);
        });
    }

    public function forceDelete(JobPost $jobPost): void
    {
        foreach ($jobPost->attachments as $att) {
            Storage::disk('public')->delete($att->path);
        }
        if ($jobPost->attachment_path) {
            Storage::disk('public')->delete($jobPost->attachment_path);
        }
        $jobPost->delete();
    }

    /** Related sellable exams/PDFs by classification (and children). */
    public function relatedCatalog(JobPost $jobPost): array
    {
        $classId = $jobPost->job_classification_id;
        if (! $classId) {
            return ['exams' => [], 'pdfs' => []];
        }

        $classification = JobClassification::query()->find($classId);
        $ids = $classification ? $classification->descendantAndSelfIds() : [$classId];

        $exams = Exam::query()
            ->whereIn('job_classification_id', $ids)
            ->where('status', 'published')
            ->orderByDesc('id')
            ->limit(12)
            ->get(['id', 'title', 'slug', 'is_free', 'price', 'duration_minutes', 'total_questions', 'job_classification_id']);

        $pdfs = PdfProduct::query()
            ->whereIn('job_classification_id', $ids)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->limit(12)
            ->get(['id', 'title', 'price', 'thumbnail', 'category', 'job_classification_id']);

        return [
            'exams' => $exams->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'slug' => $exam->slug,
                'is_free' => (bool) $exam->is_free,
                'price' => $exam->price,
                'duration_minutes' => $exam->duration_minutes,
                'total_questions' => $exam->total_questions,
                'job_classification_id' => $exam->job_classification_id,
            ])->values()->all(),
            'pdfs' => $pdfs->map(fn (PdfProduct $pdf) => [
                'id' => $pdf->id,
                'title' => $pdf->title,
                'price' => $pdf->price,
                'thumbnail' => $pdf->thumbnail,
                'category' => $pdf->category,
                'job_classification_id' => $pdf->job_classification_id,
            ])->values()->all(),
        ];
    }

    protected function storeAttachments(JobPost $jobPost, array $items): void
    {
        $order = (int) $jobPost->attachments()->max('sort_order');

        foreach ($items as $item) {
            /** @var UploadedFile|null $file */
            $file = $item['file'] ?? null;
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $order++;
            JobPostAttachment::query()->create([
                'job_post_id' => $jobPost->id,
                'path' => $file->store('job-attachments', 'public'),
                'title' => $item['title'] ?? $file->getClientOriginalName(),
                'description' => $item['description'] ?? null,
                'sort_order' => $order,
            ]);
        }
    }

    protected function removeAttachments(JobPost $jobPost, array $ids): void
    {
        $rows = $jobPost->attachments()->whereIn('id', $ids)->get();
        foreach ($rows as $row) {
            Storage::disk('public')->delete($row->path);
            $row->delete();
        }
    }

    protected function normalizePayload(array $data): array
    {
        $provinces = $data['provinces'] ?? [];
        if (! is_array($provinces)) {
            $provinces = [];
        }
        $provinces = array_values(array_unique(array_filter($provinces)));

        $data['provinces'] = $provinces;
        $data['province'] = $provinces[0] ?? null;

        if (! empty($data['job_classification_id'])) {
            $name = JobClassification::query()->whereKey($data['job_classification_id'])->value('name');
            if ($name) {
                $data['company_name'] = $name;
            }
        }

        if (! empty($data['seo_tag'])) {
            $data['seo_tag'] = $this->normalizeSeoTag((string) $data['seo_tag']);
        } else {
            $data['seo_tag'] = null;
        }

        if (! array_key_exists('job_category', $data)) {
            $data['job_category'] = null;
        }

        if (isset($data['attachment']) && $data['attachment'] instanceof UploadedFile) {
            $data['_legacy_attachment'] = $data['attachment'];
        }

        return $data;
    }

    public function normalizeSeoTag(string $tag): string
    {
        $tag = trim($tag);
        $tag = preg_replace('/\s+/u', '_', $tag) ?? $tag;
        $tag = preg_replace('/_+/u', '_', $tag) ?? $tag;

        return trim($tag, '_');
    }
}
