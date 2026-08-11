<?php

namespace App\Services\Content;

use App\Enums\Content\ContentStatus;
use App\Enums\Content\ContentType;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use Illuminate\Support\Str;

class ContentDuplicateDetector
{
    public function hash(string $title, string $content, ContentType $type, ?int $jobPostId): string
    {
        $normalized = Str::lower(preg_replace('/\s+/u', ' ', strip_tags($title.' '.$content)) ?? '');

        return hash('sha256', $type->value.'|'.($jobPostId ?? 0).'|'.$normalized);
    }

    /**
     * Find existing article for same job+type (preferred update target).
     */
    public function findExistingForJobType(JobPost $job, ContentType $type): ?GeneratedContent
    {
        return GeneratedContent::query()
            ->where('job_post_id', $job->id)
            ->where('content_type', $type->value)
            ->whereNotIn('status', [ContentStatus::Skipped->value])
            ->latest('id')
            ->first();
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $q = GeneratedContent::query()->where('slug', $slug);
        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }

        return $q->exists();
    }

    public function hashExists(string $hash, ?int $ignoreId = null): bool
    {
        $q = GeneratedContent::query()->where('content_hash', $hash);
        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }

        return $q->exists();
    }

    /**
     * Similar title within same content type (last 90 days).
     */
    public function similarTitleExists(string $title, ContentType $type, ?int $ignoreId = null): bool
    {
        $needle = Str::limit(Str::lower(preg_replace('/\s+/u', ' ', $title) ?? ''), 80, '');
        if (mb_strlen($needle) < 12) {
            return false;
        }

        $q = GeneratedContent::query()
            ->where('content_type', $type->value)
            ->where('created_at', '>=', now()->subDays(90))
            ->where('title', 'like', '%'.mb_substr($needle, 0, 40).'%');

        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }

        return $q->exists();
    }
}
