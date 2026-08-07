<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Exam extends Model
{
    use HasFactory;
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
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'has_negative_marking' => 'boolean',
            'negative_mark_ratio' => 'float',
            'price' => 'decimal:0',
            'duration_minutes' => 'integer',
            'passing_score' => 'integer',
            'total_questions' => 'integer',
            'total_marks' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Exam $exam) {
            if (blank($exam->slug)) {
                $exam->slug = Str::slug($exam->title).'-'.Str::random(5);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExamCategory::class, 'category_id');
    }

    public function jobPost(): BelongsTo
    {
        return $this->belongsTo(JobPost::class);
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(JobClassification::class, 'job_classification_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
