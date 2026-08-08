<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlerError extends Model
{
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

    public function source(): BelongsTo
    {
        return $this->belongsTo(JobSource::class, 'job_source_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(CrawlerRun::class, 'crawler_run_id');
    }
}
