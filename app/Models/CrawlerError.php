<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $job_source_id
 * @property int|null $crawler_run_id
 * @property string|null $error_type
 * @property string|null $message
 * @property string|null $url
 * @property array<string, mixed>|null $context
 * @property Carbon|null $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JobSource|null $source
 * @property-read CrawlerRun|null $run
 */
class CrawlerError extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'job_source_id',
        'crawler_run_id',
        'error_type',
        'message',
        'url',
        'context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CrawlerError $error) {
            if ($error->occurred_at === null) {
                $error->occurred_at = now();
            }
        });
    }

    /** @return BelongsTo<JobSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(JobSource::class, 'job_source_id');
    }

    /** @return BelongsTo<CrawlerRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(CrawlerRun::class, 'crawler_run_id');
    }
}
