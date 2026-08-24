<?php

namespace App\Models\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $analysis_id
 * @property string|null $type
 * @property string|null $message
 * @property string|null $priority
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
