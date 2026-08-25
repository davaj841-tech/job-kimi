<?php

namespace App\Models\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $faqable_type
 * @property int $faqable_id
 * @property string $question
 * @property string $answer
 * @property int|null $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SeoFaq extends Model
{
    protected $table = 'seo_faqs';

    protected $guarded = ['id'];

    /**
     * @return MorphTo<Model, $this>
     */
    public function faqable(): MorphTo
    {
        return $this->morphTo();
    }
}
