<?php

namespace App\Models\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $analysis_id
 * @property string|null $type
 * @property string|null $message
 * @property string|null $priority
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SeoSuggestion extends Model
{
    protected $table = 'seo_suggestions';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<SeoAnalysis, $this>
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(SeoAnalysis::class, 'analysis_id');
    }
}
