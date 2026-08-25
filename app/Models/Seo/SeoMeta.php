<?php

namespace App\Models\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $seoable_type
 * @property int $seoable_id
 * @property string|null $title
 * @property string|null $description
 * @property string|null $canonical
 * @property string|null $robots
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image
 * @property string|null $twitter_card
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $guarded = ['id'];

    protected $casts = [
        'extra' => 'array',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
