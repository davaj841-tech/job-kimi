<?php

namespace App\Services\Aggregation;

use App\Contracts\Aggregation\JobValidatorInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class JobValidator implements JobValidatorInterface
{
    public function validate(array $normalized): array
    {
        $errors = [];

        if (! filled(Arr::get($normalized, 'title'))) {
            $errors[] = 'title is required';
        } elseif (mb_strlen((string) $normalized['title']) < 3) {
            $errors[] = 'title is too short';
        }

        if (! filled(Arr::get($normalized, 'company_name'))) {
            $errors[] = 'company_name is required';
        }

        // Description may be null (unknown), but empty string after normalize is treated as missing — OK.
        // Reject records that invent placeholder-only content.
        if (Arr::get($normalized, 'company_name') === 'نامشخص') {
            $errors[] = 'company_name placeholder is not allowed';
        }

        if (Arr::get($normalized, '_had_invalid_registration_link')) {
            $errors[] = 'registration_link is invalid';
        }
        if (Arr::get($normalized, '_had_invalid_source_url')) {
            $errors[] = 'source_url is invalid';
        }

        $link = Arr::get($normalized, 'registration_link');
        if ($link !== null && $link !== '' && ! $this->isHttpUrl($link)) {
            $errors[] = 'registration_link must be a valid http(s) URL when present';
        }

        $sourceUrl = Arr::get($normalized, 'source_url');
        if ($sourceUrl !== null && $sourceUrl !== '' && ! $this->isHttpUrl($sourceUrl)) {
            $errors[] = 'source_url must be a valid http(s) URL when present';
        }

        // Must have at least one provenance URL (apply or listing) OR external_id from source
        if (! filled($link) && ! filled($sourceUrl) && ! filled(Arr::get($normalized, 'external_id'))) {
            $errors[] = 'at least one of registration_link, source_url, or external_id is required';
        }

        $start = Arr::get($normalized, 'registration_starts_at');
        $deadline = Arr::get($normalized, 'registration_deadline');
        $exam = Arr::get($normalized, 'exam_date');

        if ($start && $deadline) {
            try {
                if (Carbon::parse($deadline)->lt(Carbon::parse($start))) {
                    $errors[] = 'registration_deadline cannot be before registration_starts_at';
                }
            } catch (\Throwable) {
                $errors[] = 'invalid registration date range';
            }
        }

        if ($deadline && $exam) {
            try {
                if (Carbon::parse($exam)->lt(Carbon::parse($deadline))) {
                    $errors[] = 'exam_date cannot be before registration_deadline';
                }
            } catch (\Throwable) {
                $errors[] = 'invalid exam_date';
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    protected function isHttpUrl(string $url): bool
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL)
            && (str_starts_with(strtolower($url), 'http://') || str_starts_with(strtolower($url), 'https://'));
    }
}
