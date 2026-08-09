<?php

namespace App\Services\Aggregation;

use App\Enums\Aggregation\JobSourceReliability;
use App\Models\JobSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Source registry / whitelist helpers for the aggregator.
 * Does not perform HTTP or crawling.
 */
class JobSourceManager
{
    public function __construct(
        protected ?AggregationScheduleService $schedule = null,
    ) {
        $this->schedule ??= new AggregationScheduleService;
    }

    /**
     * @return list<string>
     */
    public function allowedDomains(): array
    {
        return JobSource::query()
            ->whitelisted()
            ->pluck('domain')
            ->filter()
            ->map(fn ($d) => Str::lower((string) $d))
            ->unique()
            ->values()
            ->all();
    }

    public function isDomainAllowed(string $hostOrUrl): bool
    {
        $host = Str::contains($hostOrUrl, '://')
            ? (JobSource::extractDomain($hostOrUrl) ?? '')
            : Str::lower($hostOrUrl);

        if ($host === '') {
            return false;
        }

        foreach ($this->allowedDomains() as $allowed) {
            if ($host === $allowed || Str::endsWith($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, JobSource>
     */
    public function dispatchableSources(): Collection
    {
        return JobSource::query()
            ->dispatchable()
            ->with(['endpoints' => fn ($q) => $q->enabled()])
            ->get();
    }

    /**
     * @return Collection<int, JobSource>
     */
    public function dispatchableSourcesDueByFrequency(): Collection
    {
        return $this->dispatchableSources()
            ->filter(fn (JobSource $source) => $this->schedule->isSourceDueByFrequency($source))
            ->values();
    }

    /**
     * @return Collection<int, JobSource>
     */
    public function dispatchableSourcesForSlot(string $slot): Collection
    {
        return $this->dispatchableSources()
            ->filter(function (JobSource $source) use ($slot) {
                if (! $this->schedule->sourceMatchesSlot($source, $slot)) {
                    return false;
                }

                return $this->schedule->isSourceDueByFrequency($source);
            })
            ->values();
    }

    public function canAutoPublish(JobSource $source): bool
    {
        return $source->allowsAutoPublish();
    }

    /**
     * @return list<string>
     */
    public function autoPublishReliabilityValues(): array
    {
        return array_map(
            fn (JobSourceReliability $r) => $r->value,
            array_values(array_filter(
                JobSourceReliability::cases(),
                fn (JobSourceReliability $r) => $r->allowsAutoPublish()
            ))
        );
    }
}
