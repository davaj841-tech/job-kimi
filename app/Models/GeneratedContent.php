<?php

namespace App\Models;

use App\Enums\Content\ContentStatus;
use App\Enums\Content\ContentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedContent extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'content_type',
        'status',
        'source_type',
        'source_id',
        'job_post_id',
        'blog_post_id',
        'published_at',
        'scheduled_for',
        'content_hash',
        'metadata',
        'generation_attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'content_type' => ContentType::class,
            'status' => ContentStatus::class,
            'metadata' => 'array',
            'published_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'generation_attempts' => 'integer',
            'blog_post_id' => 'integer',
            'source_id' => 'integer',
        ];
    }

    public function jobPost(): BelongsTo
    {
        return $this->belongsTo(JobPost::class);
    }

    public function blogPost(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPubliclyVisible(): bool
    {
        if ($this->status !== ContentStatus::Published) {
            return false;
        }

        return $this->published_at === null || $this->published_at->lte(now());
    }

    public function publicUrl(): string
    {
        return url('/articles/'.$this->slug);
    }
}
