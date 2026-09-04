<?php

namespace App\Models;

use App\Traits\HasSeo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $job_post_id
 * @property int|null $job_classification_id
 * @property string $title
 * @property string|null $description
 * @property string|null $file_path
 * @property string|null $thumbnail
 * @property numeric-string|float|int|null $price
 * @property string|null $category
 * @property int|null $download_count
 * @property bool|null $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $thumbnail_url
 * @property-read JobPost|null $jobPost
 * @property-read JobClassification|null $classification
 * @property-read Collection<int, PdfPurchase> $purchases
 * @property bool|null $is_purchased
 * @property string|null $purchase_date
 * @property string|null $download_url
 * @property-read int|null $purchases_count
 */
class PdfProduct extends Model
{
    use HasSeo;
    use SoftDeletes;

    protected $fillable = [
        'job_post_id',
        'job_classification_id',
        'title',
        'description',
        'file_path',
        'attachments',
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
            'attachments' => 'array',
        ];
    }

    /** @return BelongsTo<JobPost, $this> */
    public function jobPost(): BelongsTo
    {
        return $this->belongsTo(JobPost::class);
    }

    /** @return BelongsTo<JobClassification, $this> */
    public function classification(): BelongsTo
    {
        return $this->belongsTo(JobClassification::class, 'job_classification_id');
    }

    /** @return HasMany<PdfPurchase, $this> */
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
