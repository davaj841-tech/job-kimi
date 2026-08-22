<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobDuplicate extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    protected $fillable = [
        'original_job_post_id',
        'duplicate_job_post_id',
        'similarity_score',
        'detection_reason',
    ];

    protected function casts(): array
    {
        return [
            'similarity_score' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<JobPost, $this> */
    public function originalJob(): BelongsTo
    {
        return $this->belongsTo(JobPost::class, 'original_job_post_id');
    }

    /** @return BelongsTo<JobPost, $this> */
    public function duplicateJob(): BelongsTo
    {
        return $this->belongsTo(JobPost::class, 'duplicate_job_post_id');
    }
}
