<?php

namespace App\Models;

use App\Enums\Aggregation\JobEndpointType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $job_source_id
 * @property string|null $url
 * @property JobEndpointType|null $endpoint_type
 * @property string|null $http_method
 * @property string|null $parser_type
 * @property bool $is_enabled
 * @property int|null $sort_order
 */
class JobSourceEndpoint extends Model
{
    /** @use HasFactory<\Database\Factories\JobSourceEndpointFactory> */
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

    /**
     * @return BelongsTo<JobSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(JobSource::class, 'job_source_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
