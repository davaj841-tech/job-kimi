<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image',
        'category',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

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
