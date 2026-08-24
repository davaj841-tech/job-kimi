<?php

namespace App\Models;

use App\Enums\Aggregation\JobCrawlerType;
use App\Enums\Aggregation\JobSourceQualityStatus;
use App\Enums\Aggregation\JobSourceReliability;
use App\Enums\Aggregation\JobSourceType;
use Database\Factories\JobSourceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string|null $slug
 * @property string|null $official_url
 * @property string|null $domain
 * @property JobSourceType|null $source_type
 * @property JobSourceReliability|null $reliability_level
 * @property JobSourceQualityStatus|null $quality_status
 * @property JobCrawlerType|null $crawler_type
 * @property bool $is_enabled
 * @property bool $is_approved
 * @property Carbon|null $last_crawled_at
 * @property Carbon|null $last_success_at
 * @property Carbon|null $last_failure_at
 * @property Carbon|null $health_backoff_until
 * @property int|null $consecutive_failures
 * @property string|null $schedule_mode
 * @property string|null $crawl_frequency
 * @property array<int, array{time?: string, enabled?: bool, label?: string|null}>|null $custom_schedule_times
 * @property-read Collection<int, JobSourceEndpoint> $endpoints
 *
 * @method static Builder<static> enabled()
 * @method static Builder<static> approved()
 * @method static Builder<static> whitelisted()
 * @method static Builder<static> dispatchable()
 * @method static Builder<static> ofQualityStatus(JobSourceQualityStatus|string $status)
 * @method static Builder<static> ofReliability(JobSourceReliability|string $level)
 */
class JobSource extends Model
{
    /** @use HasFactory<JobSourceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'official_url',
        'domain',
        'source_type',
        'reliability_level',
        'priority',
        'is_enabled',
        'is_approved',
        'quality_status',
        'crawler_type',
        'crawl_frequency',
        'schedule_mode',
        'custom_schedule_times',
        'last_crawled_at',
        'last_success_at',
        'last_failure_at',
        'notes',
        'quality_notes',
        'consecutive_failures',
        'consecutive_empty_crawls',
        'total_successful_crawls',
        'total_failed_crawls',
        'total_empty_successful_crawls',
        'lifetime_jobs_found',
        'lifetime_jobs_created',
        'lifetime_jobs_updated',
        'lifetime_duplicates',
        'lifetime_rejected',
        'lifetime_validation_errors',
        'last_http_status',
        'last_crawl_outcome',
        'health_backoff_until',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => JobSourceType::class,
            'reliability_level' => JobSourceReliability::class,
            'quality_status' => JobSourceQualityStatus::class,
            'crawler_type' => JobCrawlerType::class,
            'priority' => 'integer',
            'is_enabled' => 'boolean',
            'is_approved' => 'boolean',
            'custom_schedule_times' => 'array',
            'last_crawled_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'health_backoff_until' => 'datetime',
            'consecutive_failures' => 'integer',
            'consecutive_empty_crawls' => 'integer',
            'total_successful_crawls' => 'integer',
            'total_failed_crawls' => 'integer',
            'total_empty_successful_crawls' => 'integer',
            'lifetime_jobs_found' => 'integer',
            'lifetime_jobs_created' => 'integer',
            'lifetime_jobs_updated' => 'integer',
            'lifetime_duplicates' => 'integer',
            'lifetime_rejected' => 'integer',
            'lifetime_validation_errors' => 'integer',
            'last_http_status' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (JobSource $source) {
            if (blank($source->slug) && filled($source->name)) {
                $source->slug = Str::slug($source->name) ?: 'source-'.Str::random(6);
            }
            if (blank($source->domain) && filled($source->official_url)) {
                $source->domain = static::extractDomain($source->official_url);
            }
        });
    }

    public static function extractDomain(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host ? Str::lower($host) : null;
    }

    /**
     * @return HasMany<JobSourceEndpoint, $this>
     */
    public function endpoints(): HasMany
    {
        return $this->hasMany(JobSourceEndpoint::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<CrawlerRun, $this> */
    public function crawlerRuns(): HasMany
    {
        return $this->hasMany(CrawlerRun::class);
    }

    /** @return HasMany<CrawlerError, $this> */
    public function crawlerErrors(): HasMany
    {
        return $this->hasMany(CrawlerError::class);
    }

    /** @return HasMany<JobPost, $this> */
    public function jobPosts(): HasMany
    {
        return $this->hasMany(JobPost::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    /**
     * Admin whitelist: enabled + approved (domain allowlist members).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhitelisted(Builder $query): Builder
    {
        return $query->enabled()->approved();
    }

    /**
     * Sources that may be dispatched by the aggregator (whitelist + crawlable quality + not in backoff).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDispatchable(Builder $query): Builder
    {
        return $query->whitelisted()
            ->whereIn('quality_status', [
                JobSourceQualityStatus::Active->value,
                JobSourceQualityStatus::Limited->value,
            ])
            ->where(function (Builder $q) {
                $q->whereNull('health_backoff_until')
                    ->orWhere('health_backoff_until', '<=', now());
            })
            ->orderBy('priority')
            ->orderBy('id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfQualityStatus(Builder $query, JobSourceQualityStatus|string $status): Builder
    {
        $value = $status instanceof JobSourceQualityStatus ? $status->value : $status;

        return $query->where('quality_status', $value);
    }

    public function allowsAutomaticCrawl(): bool
    {
        if (! $this->is_enabled || ! $this->is_approved) {
            return false;
        }
        if (! ($this->quality_status?->allowsAutomaticCrawl() ?? true)) {
            return false;
        }
        if ($this->health_backoff_until && $this->health_backoff_until->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfReliability(Builder $query, JobSourceReliability|string $level): Builder
    {
        $value = $level instanceof JobSourceReliability ? $level->value : $level;

        return $query->where('reliability_level', $value);
    }

    public function allowsAutoPublish(): bool
    {
        return $this->is_approved
            && $this->is_enabled
            && ($this->reliability_level?->allowsAutoPublish() ?? false);
    }
}
