<?php

namespace App\Services\Aggregation\Parsers;

use App\Contracts\Aggregation\JobParserInterface;
use Illuminate\Support\Arr;

class JsonFeedParser implements JobParserInterface
{
    public function parserType(): string
    {
        return 'json';
    }

    public function parse(mixed $payload, array $context = []): array
    {
        $data = $payload;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (! is_array($decoded)) {
                return [];
            }
            $data = $decoded;
        }

        if (! is_array($data)) {
            return [];
        }

        $list = $this->extractList($data);
        $items = [];

        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = (string) Arr::get($row, 'title', Arr::get($row, 'name', ''));
            if (trim($title) === '') {
                continue;
            }

            $link = Arr::get($row, 'registration_link')
                ?? Arr::get($row, 'apply_url')
                ?? Arr::get($row, 'application_url')
                ?? Arr::get($row, 'url')
                ?? Arr::get($row, 'link')
                ?? null;

            // Keep apply URL and listing/provenance URL separate.
            // Do not invent a per-job source_url from the shared feed endpoint (causes false duplicates).
            $sourceUrl = Arr::get($row, 'source_url')
                ?? Arr::get($row, 'listing_url')
                ?? null;

            $items[] = [
                'title' => trim($title),
                'company_name' => Arr::get($row, 'company_name')
                    ?? Arr::get($row, 'organization')
                    ?? Arr::get($row, 'company')
                    ?? ($context['source_name'] ?? null),
                'description' => (string) (Arr::get($row, 'description') ?? Arr::get($row, 'body') ?? Arr::get($row, 'summary') ?? ''),
                'requirements' => Arr::get($row, 'requirements') ?? Arr::get($row, 'requirement'),
                'education' => Arr::get($row, 'education') ?? Arr::get($row, 'degree'),
                'field_of_study' => Arr::get($row, 'field_of_study') ?? Arr::get($row, 'major'),
                'experience' => Arr::get($row, 'experience') ?? Arr::get($row, 'experience_years'),
                'employment_type' => Arr::get($row, 'employment_type') ?? Arr::get($row, 'contract_type'),
                'province' => Arr::get($row, 'province'),
                'city' => Arr::get($row, 'city'),
                'job_category' => Arr::get($row, 'job_category') ?? Arr::get($row, 'category'),
                'registration_starts_at' => Arr::get($row, 'registration_starts_at') ?? Arr::get($row, 'registration_start') ?? Arr::get($row, 'start_date'),
                'registration_deadline' => Arr::get($row, 'registration_deadline') ?? Arr::get($row, 'deadline'),
                'exam_date' => Arr::get($row, 'exam_date'),
                'published_at' => Arr::get($row, 'published_at') ?? Arr::get($row, 'publication_date') ?? Arr::get($row, 'pubDate'),
                'registration_link' => $link,
                'source_url' => $sourceUrl,
                '_endpoint_url' => $context['endpoint_url'] ?? null,
                'external_id' => Arr::get($row, 'external_id') ?? Arr::get($row, 'id') ?? null,
            ];
        }

        return $items;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<int, mixed>
     */
    protected function extractList(array $data): array
    {
        if (array_is_list($data)) {
            return $data;
        }

        foreach (['data', 'jobs', 'items', 'results', 'vacancies'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return array_is_list($data[$key]) ? $data[$key] : [$data[$key]];
            }
        }

        return [$data];
    }
}
