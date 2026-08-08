<?php

namespace App\Services\Aggregation;

use App\Contracts\Aggregation\DuplicateDetectorInterface;
use App\Models\JobPost;
use App\Services\Aggregation\Support\PersianText;
use Illuminate\Support\Arr;

class DuplicateDetector implements DuplicateDetectorInterface
{
    public function findDuplicate(array $normalized): array
    {
        $query = JobPost::query();
        $sourceId = Arr::get($normalized, 'job_source_id');

        // 1) Strongest: same source + external_id
        $externalId = Arr::get($normalized, 'external_id');
        if (filled($sourceId) && filled($externalId)) {
            $match = (clone $query)
                ->where('job_source_id', $sourceId)
                ->where('external_id', $externalId)
                ->first();
            if ($match) {
                return $this->hit($match, 100, 'source_external_id');
            }
        }

        // 2) Application URL
        $link = Arr::get($normalized, 'registration_link');
        if (filled($link)) {
            $match = (clone $query)->where('registration_link', $link)->first();
            if ($match) {
                return $this->hit($match, 98, 'registration_link');
            }
        }

        // 3) Listing/source URL — only when it looks like a per-job URL (not a shared feed endpoint)
        $sourceUrl = Arr::get($normalized, 'source_url');
        $endpointUrl = Arr::get($normalized, '_endpoint_url');
        if (filled($sourceUrl) && $sourceUrl !== $endpointUrl) {
            $match = (clone $query)->where('source_url', $sourceUrl)->first();
            if ($match) {
                return $this->hit($match, 96, 'source_url');
            }
        }

        // 4) Content hash (normalized title/org/urls/deadline/external_id)
        $hash = Arr::get($normalized, 'content_hash');
        if (filled($hash)) {
            $match = (clone $query)->where('content_hash', $hash)->first();
            if ($match) {
                return $this->hit($match, 95, 'content_hash');
            }
        }

        // 5) Normalized title + organization + deadline (never title alone)
        $titleKey = Arr::get($normalized, 'title_key') ?: PersianText::normalizeKey(Arr::get($normalized, 'title'));
        $orgKey = Arr::get($normalized, 'organization_key') ?: PersianText::normalizeKey(Arr::get($normalized, 'company_name'));
        $deadline = Arr::get($normalized, 'registration_deadline');

        if (filled($titleKey) && filled($orgKey) && filled($deadline)) {
            try {
                $deadlineDate = \Illuminate\Support\Carbon::parse($deadline)->toDateString();
            } catch (\Throwable) {
                $deadlineDate = null;
            }

            if ($deadlineDate) {
                $candidates = (clone $query)
                    ->whereDate('registration_deadline', $deadlineDate)
                    ->whereNotNull('title')
                    ->whereNotNull('company_name')
                    ->limit(50)
                    ->get();

                foreach ($candidates as $candidate) {
                    if (
                        PersianText::normalizeKey($candidate->title) === $titleKey
                        && PersianText::normalizeKey($candidate->company_name) === $orgKey
                    ) {
                        return $this->hit($candidate, 90, 'title_org_deadline');
                    }
                }
            }
        }

        return [
            'is_duplicate' => false,
            'original' => null,
            'score' => null,
            'reason' => null,
        ];
    }

    protected function hit(JobPost $original, float $score, string $reason): array
    {
        return [
            'is_duplicate' => true,
            'original' => $original,
            'score' => $score,
            'reason' => $reason,
        ];
    }
}
