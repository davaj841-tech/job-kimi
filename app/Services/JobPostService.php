<?php

namespace App\Services;

use App\Events\JobPostApproved;
use App\Models\JobClassification;
use App\Models\JobPost;
use App\Models\JobPostAttachment;
use App\Repositories\JobPostRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class JobPostService
{
    public function __construct(
        protected JobPostRepository $jobPostRepository
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, JobPost>
     */
    public function getPublicList(array $filters): LengthAwarePaginator
    {
        return $this->jobPostRepository->getApproved($filters);
    }

    /**
     * @return Collection<int, JobPost>
     */
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

        JobPostsCache::forget();

        event(new JobPostApproved($jobPost));

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

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, UploadedFile|string|null>>  $newAttachments
     */
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

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, UploadedFile|string|null>>  $newAttachments
     */
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

    /**
     * Related sellable exams/PDFs by classification (and children).
     *
     * @return array<string, mixed>
     */
    public function relatedCatalog(JobPost $jobPost): array
    {
        return app(CatalogAttachService::class)->resolve(
            $jobPost->job_classification_id ? (int) $jobPost->job_classification_id : null,
            (bool) ($jobPost->auto_catalog ?? true),
            $jobPost->exam_ids ?? [],
            $jobPost->pdf_ids ?? []
        );
    }

    /**
     * @param  array<int, array<string, UploadedFile|string|null>>  $items
     */
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

    /**
     * @param  array<string, mixed>  $ids
     */
    protected function removeAttachments(JobPost $jobPost, array $ids): void
    {
        $rows = $jobPost->attachments()->whereIn('id', $ids)->get();
        foreach ($rows as $row) {
            Storage::disk('public')->delete($row->path);
            $row->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizePayload(array $data): array
    {
        if (array_key_exists('provinces', $data)) {
            $provinces = $data['provinces'] ?? [];
            if (! is_array($provinces)) {
                $provinces = [];
            }
            $provinces = array_values(array_unique(array_filter($provinces)));
            $data['provinces'] = $provinces;
            $data['province'] = $provinces[0] ?? ($data['province'] ?? null);
        }

        if (! empty($data['job_classification_id'])) {
            $name = JobClassification::query()->whereKey($data['job_classification_id'])->value('name');
            if ($name) {
                $data['company_name'] = $name;
            }
        }

        if (array_key_exists('seo_tag', $data)) {
            if (! empty($data['seo_tag'])) {
                $data['seo_tag'] = $this->normalizeSeoTag((string) $data['seo_tag']);
            } else {
                $data['seo_tag'] = null;
            }
        }

        if (array_key_exists('exam_ids', $data)) {
            $data['exam_ids'] = app(CatalogAttachService::class)->intIds($data['exam_ids']);
        }
        if (array_key_exists('pdf_ids', $data)) {
            $data['pdf_ids'] = app(CatalogAttachService::class)->intIds($data['pdf_ids']);
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
