<?php

namespace App\Models;

use App\Enums\Aggregation\CrawlerRunStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $job_source_id
 * @property CrawlerRunStatus|null $status
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $jobs_found
 * @property int|null $jobs_created
 * @property int|null $jobs_updated
 * @property int|null $duplicates
 * @property int|null $errors_count
 * @property int|null $execution_ms
 * @property array<string, mixed>|null $meta
 * @property-read JobSource|null $source
 */
class CrawlerRun extends Model
{
    /** @use HasFactory<\Database\Factories\CrawlerRunFactory> */
    use HasFactory;

    protected $fillable = [
        'job_source_id',
        'status',
        'started_at',
        'finished_at',
        'jobs_found',
        'jobs_created',
        'jobs_updated',
        'duplicates',
        'errors_count',
        'execution_ms',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => CrawlerRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'jobs_found' => 'integer',
            'jobs_created' => 'integer',
            'jobs_updated' => 'integer',
            'duplicates' => 'integer',
            'errors_count' => 'integer',
            'execution_ms' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<JobSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(JobSource::class, 'job_source_id');
    }

    /**
     * @return HasMany<CrawlerError, $this>
     */
    public function errors(): HasMany
    {
        return $this->hasMany(CrawlerError::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeStatus(Builder $query, CrawlerRunStatus|string $status): Builder
    {
        $value = $status instanceof CrawlerRunStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    public function markRunning(): void
    {
        $this->update([
            'status' => CrawlerRunStatus::Running,
            'started_at' => $this->started_at ?? now(),
        ]);
    }

    public function markFinished(CrawlerRunStatus $status = CrawlerRunStatus::Completed): void
    {
        $started = $this->started_at ?? now();
        $finished = now();

        $this->update([
            'status' => $status,
            'started_at' => $started,
            'finished_at' => $finished,
            'execution_ms' => (int) $started->diffInMilliseconds($finished),
        ]);
    }
}
