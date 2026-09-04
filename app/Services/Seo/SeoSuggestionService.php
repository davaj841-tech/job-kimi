<?php

namespace App\Services\Seo;

use App\Models\Seo\SeoAnalysis;
use App\Models\Seo\SeoSuggestion;

class SeoSuggestionService
{
    /** @var array<string, array{type: string, severity: string, field: string|null}> */
    protected array $checkMap = [
        'title' => ['type' => 'title', 'severity' => 'critical', 'field' => 'title'],
        'description' => ['type' => 'description', 'severity' => 'critical', 'field' => 'description'],
        'h1' => ['type' => 'content', 'severity' => 'warning', 'field' => 'content'],
        'keyword_in_title' => ['type' => 'keyword', 'severity' => 'warning', 'field' => 'focus_keyword'],
        'keyword_in_description' => ['type' => 'keyword', 'severity' => 'warning', 'field' => 'description'],
        'keyword_in_content' => ['type' => 'keyword', 'severity' => 'info', 'field' => 'content'],
        'content_length' => ['type' => 'content', 'severity' => 'warning', 'field' => 'content'],
        'images' => ['type' => 'image', 'severity' => 'info', 'field' => 'content'],
        'internal_links' => ['type' => 'link', 'severity' => 'info', 'field' => 'content'],
        'schema' => ['type' => 'schema', 'severity' => 'warning', 'field' => null],
        'canonical' => ['type' => 'technical', 'severity' => 'info', 'field' => 'canonical'],
    ];

    public function syncFromAnalysis(SeoAnalysis $analysis): void
    {
        $checks = $analysis->checks ?? [];
        if (! is_array($checks)) {
            return;
        }

        SeoSuggestion::query()->where('analysis_id', $analysis->id)->delete();

        foreach ($checks as $key => $check) {
            if (! is_array($check) || ($check['pass'] ?? false)) {
                continue;
            }

            $map = $this->checkMap[$key] ?? ['type' => 'technical', 'severity' => 'info', 'field' => null];
            $message = (string) ($check['message'] ?? 'بهبود SEO پیشنهاد می‌شود');

            SeoSuggestion::query()->create([
                'analysis_id' => $analysis->id,
                'type' => $map['type'],
                'severity' => $map['severity'],
                'message' => mb_substr($message, 0, 500),
                'field' => $map['field'],
            ]);
        }
    }
}
