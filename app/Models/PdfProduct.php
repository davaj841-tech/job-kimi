<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'job_post_id',
        'job_classification_id',
        'title',
        'description',
        'file_path',
        'thumbnail',
        'price',
        'category',
        'download_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:0',
            'is_active' => 'boolean',
            'download_count' => 'integer',
        ];
    }

    public function jobPost(): BelongsTo
    {
        return $this->belongsTo(JobPost::class);
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(JobClassification::class, 'job_classification_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PdfPurchase::class);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail) {
            return null;
        }

        if (Str::startsWith($this->thumbnail, ['http://', 'https://'])) {
            return $this->thumbnail;
        }

        return Storage::disk('public')->url($this->thumbnail);
    }
}
