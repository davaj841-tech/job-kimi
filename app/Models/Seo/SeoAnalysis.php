<?php

namespace App\Models\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $analyzable_type
 * @property int $analyzable_id
 * @property int|null $score
 * @property string|null $status
 * @property array<string, mixed>|null $checks
 * @property \Illuminate\Support\Carbon|null $analyzed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|null $analyzable
 * @property-read string $status_label
 */
class SeoAnalysis extends Model
{
    protected $table = 'seo_analyses';

    protected $guarded = ['id'];

    protected $casts = [
        'checks' => 'array',
        'analyzed_at' => 'datetime',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function analyzable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<SeoSuggestion, $this>
     */
    public function suggestions(): HasMany
    {
        return $this->hasMany(SeoSuggestion::class, 'analysis_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'excellent' => 'عالی',
            'good' => 'خوب',
            'needs_improvement' => 'نیاز به بهبود',
            'poor' => 'ضعیف',
            default => 'در انتظار',
        };
    }
}
