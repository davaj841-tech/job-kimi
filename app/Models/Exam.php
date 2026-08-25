<?php

namespace App\Models;

use App\Observers\ExamObserver;
use App\Traits\HasSeo;
use App\Traits\HasUniqueSlug;
use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property string|null $slug
 * @property string|null $seo_tag
 * @property int|null $category_id
 * @property int|null $job_post_id
 * @property int|null $job_classification_id
 * @property string|null $description
 * @property int|null $duration_minutes
 * @property int|null $passing_score
 * @property int|null $total_questions
 * @property int|null $total_marks
 * @property bool $has_negative_marking
 * @property float|null $negative_mark_ratio
 * @property bool $is_free
 * @property numeric-string|float|int|null $price
 * @property bool|null $subscription_required
 * @property string|null $status
 * @property bool $is_random
 * @property array<string, mixed>|null $random_config
 * @property int|null $created_by
 * @property float|int|null $user_best_score
 * @property bool|null $is_eligible
 * @property int|null $user_attempt_count
 */
#[ObservedBy([ExamObserver::class])]
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory;

    use HasSeo;
    use HasUniqueSlug;

    protected string $slugSourceField = 'title';

    protected $fillable = [
        'title',
        'slug',
        'seo_tag',
        'category_id',
        'job_post_id',
        'job_classification_id',
        'description',
        'duration_minutes',
        'passing_score',
        'total_questions',
        'total_marks',
        'has_negative_marking',
        'negative_mark_ratio',
        'is_free',
        'price',
        'subscription_required',
        'status',
        'is_random',
        'random_config',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'is_random' => 'boolean',
            'random_config' => 'array',
            'has_negative_marking' => 'boolean',
            'negative_mark_ratio' => 'float',
            'price' => 'decimal:0',
            'duration_minutes' => 'integer',
            'passing_score' => 'integer',
            'total_questions' => 'integer',
            'total_marks' => 'integer',
        ];
    }

    /** @return BelongsTo<ExamCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExamCategory::class, 'category_id');
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /** @return HasMany<ExamAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
