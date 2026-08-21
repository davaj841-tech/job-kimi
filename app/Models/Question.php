<?php

namespace App\Models;

use App\Traits\HasSeo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $exam_id
 * @property string|null $question_text
 * @property string|null $question_type
 * @property string|null $option_a
 * @property string|null $option_b
 * @property string|null $option_c
 * @property string|null $option_d
 * @property string|int|null $correct_answer
 * @property string|null $explanation
 * @property string|null $difficulty
 * @property string|null $subject
 * @property string|null $source
 * @property string|int|null $exam_year
 * @property int|null $times_served
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Exam|null $exam
 * @property-read int|null $total
 */
class Question extends Model
{
    use HasFactory;
    use \App\Traits\HasSeo;

    protected $fillable = [
        'exam_id',
        'question_text',
        'question_type',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'correct_answer',
        'explanation',
        'difficulty',
        'subject',
        'source',
        'exam_year',
        'times_served',
    ];

    protected function casts(): array
    {
        return [
            'times_served' => 'integer',
        ];
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
