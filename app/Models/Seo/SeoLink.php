<?php

namespace App\Models\Seo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $linkable_type
 * @property int $linkable_id
 * @property string|null $url
 * @property string|null $anchor_text
 * @property string|null $target_type
 * @property bool $is_broken
 * @property \Illuminate\Support\Carbon|null $checked_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SeoLink extends Model
{
    protected $table = 'seo_links';

    protected $guarded = ['id'];

    protected $casts = [
        'is_broken' => 'boolean',
        'checked_at' => 'datetime',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBroken(Builder $query): Builder
    {
        return $query->where('is_broken', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInternal(Builder $query): Builder
    {
        return $query->where('target_type', 'internal');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeExternal(Builder $query): Builder
    {
        return $query->where('target_type', 'external');
    }
}
