<?php

namespace App\Models;

use App\Traits\HasSeo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string|null $slug
 * @property string|null $content
 * @property string|null $excerpt
 * @property string|null $featured_image
 * @property string|null $category
 * @property int|null $job_classification_id
 * @property bool|null $auto_catalog
 * @property array<int, int>|null $exam_ids
 * @property array<int, int>|null $pdf_ids
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $status
 * @property int|null $ai_content_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $featured_image_url
 * @property-read User|null $creator
 * @property-read AiContent|null $aiContent
 * @property list<array<string, mixed>>|null $catalog_exams
 * @property list<array<string, mixed>>|null $catalog_pdfs
 * @property array{id: int, title: string, slug: string}|null $prev_post
 * @property array{id: int, title: string, slug: string}|null $next_post
 */
class BlogPost extends Model
{
    use HasSeo;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image',
        'category',
        'job_classification_id',
        'auto_catalog',
        'exam_ids',
        'pdf_ids',
        'meta_title',
        'meta_description',
        'status',
        'ai_content_id',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (BlogPost $post) {
            if (blank($post->slug)) {
                $post->slug = Str::slug($post->title).'-'.Str::random(5);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'exam_ids' => 'array',
            'pdf_ids' => 'array',
            'auto_catalog' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<AiContent, $this>
     */
    public function aiContent(): BelongsTo
    {
        return $this->belongsTo(AiContent::class);
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        if (! $this->featured_image) {
            return null;
        }

        if (Str::startsWith($this->featured_image, ['http://', 'https://'])) {
            return $this->featured_image;
        }

        return Storage::disk('public')->url($this->featured_image);
    }
}
