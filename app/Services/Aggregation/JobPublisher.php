<?php

namespace App\Services\Aggregation;

use App\Contracts\Aggregation\JobPublisherInterface;
use App\Models\JobPost;
use App\Models\JobSource;

/**
 * Persists aggregated jobs into canonical job_posts.
 * Always pending — never auto-approve.
 */
class JobPublisher implements JobPublisherInterface
{
    /**
     * @param  array<string, mixed>  $normalized
     */
    public function publish(array $normalized, JobSource $source, bool $autoApprove = false): JobPost
    {
        unset($autoApprove);

        return JobPost::query()->create($this->payload($normalized, $source));
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    public function updateExisting(JobPost $post, array $normalized, JobSource $source): JobPost
    {
        // Refuse to mutate manual (null source) or foreign-source posts.
        if ($post->job_source_id === null || (int) $post->job_source_id !== (int) $source->id) {
            throw new \RuntimeException('Refusing to overwrite a job post that does not belong to this aggregation source.');
        }

        $post->forceFill($this->payload($normalized, $source, $post))->save();

        return $post->fresh();
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    protected function payload(array $normalized, JobSource $source, ?JobPost $existing = null): array
    {
        // Official provenance is attached via job_source_id.
        // Do not invent source_url from the shared official_url (false duplicate risk).
        $sourceUrl = array_key_exists('source_url', $normalized)
            ? $normalized['source_url']
            : $existing?->source_url;

        return [
            'title' => $normalized['title'],
            'company_name' => $normalized['company_name'] ?? $source->name,
            'description' => $normalized['description'] ?? $existing?->description,
            'requirements' => array_key_exists('requirements', $normalized)
                ? $normalized['requirements']
                : $existing?->requirements,
            'education' => array_key_exists('education', $normalized) ? $normalized['education'] : $existing?->education,
            'field_of_study' => array_key_exists('field_of_study', $normalized) ? $normalized['field_of_study'] : $existing?->field_of_study,
            'experience' => array_key_exists('experience', $normalized) ? $normalized['experience'] : $existing?->experience,
            'employment_type' => array_key_exists('employment_type', $normalized) ? $normalized['employment_type'] : $existing?->employment_type,
            'province' => array_key_exists('province', $normalized) ? $normalized['province'] : $existing?->province,
            'provinces' => array_key_exists('provinces', $normalized) ? $normalized['provinces'] : $existing?->provinces,
            'city' => array_key_exists('city', $normalized) ? $normalized['city'] : $existing?->city,
            'job_category' => array_key_exists('job_category', $normalized) ? $normalized['job_category'] : $existing?->job_category,
            'registration_starts_at' => array_key_exists('registration_starts_at', $normalized)
                ? $normalized['registration_starts_at']
                : $existing?->registration_starts_at,
            'registration_deadline' => array_key_exists('registration_deadline', $normalized)
                ? $normalized['registration_deadline']
                : $existing?->registration_deadline,
            'exam_date' => array_key_exists('exam_date', $normalized) ? $normalized['exam_date'] : $existing?->exam_date,
            'published_at' => array_key_exists('published_at', $normalized) ? $normalized['published_at'] : $existing?->published_at,
            'registration_link' => array_key_exists('registration_link', $normalized)
                ? $normalized['registration_link']
                : $existing?->registration_link,
            'source_url' => $sourceUrl,
            'job_source_id' => $source->id,
            'external_id' => array_key_exists('external_id', $normalized) ? $normalized['external_id'] : $existing?->external_id,
            'content_hash' => array_key_exists('content_hash', $normalized) ? $normalized['content_hash'] : $existing?->content_hash,
            'status' => $existing !== null ? $existing->status : 'pending',
            'is_featured' => $existing !== null ? $existing->is_featured : false,
            'created_by' => $existing?->created_by,
            'approved_by' => $existing?->approved_by,
        ];
    }
}
