<?php

namespace App\Models;

use App\Enums\Content\ContentStatus;
use App\Enums\Content\ContentType;
use App\Traits\HasSeo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $slug
 * @property string|null $excerpt
 * @property string|null $content
 * @property ContentType|null $content_type
 * @property ContentStatus|null $status
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $job_post_id
 * @property int|null $job_classification_id
 * @property bool|null $auto_catalog
 * @property array<int, int>|null $exam_ids
 * @property array<int, int>|null $pdf_ids
 * @property int|null $blog_post_id
 * @property Carbon|null $published_at
 * @property Carbon|null $scheduled_for
 * @property string|null $content_hash
 * @property array<string, mixed>|null $metadata
 * @property int|null $generation_attempts
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JobPost|null $jobPost
 * @property-read BlogPost|null $blogPost
 *
 * @method static Builder<static> published()
 */
class GeneratedContent extends Model
{
    use HasSeo;

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
        'job_classification_id',
        'auto_catalog',
        'exam_ids',
        'pdf_ids',
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
            'exam_ids' => 'array',
            'pdf_ids' => 'array',
            'auto_catalog' => 'boolean',
            'published_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'generation_attempts' => 'integer',
            'blog_post_id' => 'integer',
            'source_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<JobPost, $this>
     */
    public function jobPost(): BelongsTo
    {
        return $this->belongsTo(JobPost::class);
    }

    /**
     * @return BelongsTo<BlogPost, $this>
     */
    public function blogPost(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
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
