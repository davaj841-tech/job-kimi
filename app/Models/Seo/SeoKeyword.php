<?php

namespace App\Models\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $keywordable_type
 * @property int $keywordable_id
 * @property string|null $focus_keyword
 * @property array<int, string>|null $related_keywords
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|null $keywordable
 * @property-read int|string|null $count Aggregate alias from selectRaw queries
 */
class SeoKeyword extends Model
{
    protected $table = 'seo_keywords';

    protected $guarded = ['id'];

    protected $casts = [
        'related_keywords' => 'array',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function keywordable(): MorphTo
    {
        return $this->morphTo();
    }
}
