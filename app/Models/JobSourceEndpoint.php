<?php

namespace App\Models;

use App\Enums\Aggregation\JobEndpointType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSourceEndpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_source_id',
        'url',
        'endpoint_type',
        'http_method',
        'parser_type',
        'is_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'endpoint_type' => JobEndpointType::class,
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(JobSource::class, 'job_source_id');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
