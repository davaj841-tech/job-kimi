<?php

namespace App\Services\Content;

use App\Models\JobPost;
use Illuminate\Support\Str;

/**
 * Renders Persian templates with verified JobPost fields only.
 * Never invents missing values — placeholders become empty / omitted.
 * All placeholder values are HTML-escaped unless the key ends with _html.
 */
class ContentRenderer
{
    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, string>
     */
    public function contextFromJob(JobPost $job, array $extra = []): array
    {
        $job->loadMissing(['source:id,name,domain,official_url,reliability_level,is_approved,is_enabled']);

        // Never invent organization/title — empty values are omitted by render().
        $org = $this->clean($job->company_name);
        $title = $this->clean($job->title);

        $base = [
            'organization' => $org,
            'title' => $title,
            'province' => $this->clean($job->province) ?: $this->provincesList($job),
            'city' => $this->clean($job->city),
            'education' => $this->clean($job->education),
            'field_of_study' => $this->clean($job->field_of_study),
            'experience' => $this->clean($job->experience),
            'employment_type' => $this->clean($job->employment_type),
            'job_category' => $this->clean($job->job_category),
            'requirements' => $this->cleanMultiline($job->requirements),
            'description' => $this->cleanMultiline($job->description),
            'registration_starts_at' => $this->faDate($job->registration_starts_at),
            'registration_deadline' => $this->faDate($job->registration_deadline),
            'exam_date' => $this->faDate($job->exam_date),
            'published_at' => $this->faDate($job->published_at),
            'registration_link' => $this->safeUrl($job->registration_link),
            'source_url' => $this->safeUrl($job->source_url),
            'source_name' => $this->clean($job->source?->name),
            'source_domain' => $this->clean($job->source?->domain),
        ];

        foreach ($extra as $k => $v) {
            $base[$k] = is_scalar($v) || $v === null ? (string) ($v ?? '') : '';
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function render(string $template, array $context): string
    {
        $out = preg_replace_callback('/\{([a-z0-9_]+)\}/u', function (array $m) use ($context) {
            $key = $m[1];
            $value = $context[$key] ?? '';
            if (! is_scalar($value)) {
                return '';
            }
            $value = (string) $value;
            // Trusted pre-escaped HTML fragments only (e.g. weekly_list_html).
            if (str_ends_with($key, '_html')) {
                return $value;
            }

            return $this->e($value);
        }, $template) ?? $template;

        $lines = preg_split("/\r\n|\n|\r/", $out) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                $kept[] = '';
                continue;
            }
            if (preg_match('/[:：]\s*$/u', $trim)) {
                continue;
            }
            if (str_contains($trim, '{{missing}}')) {
                continue;
            }
            $kept[] = $line;
        }

        $text = implode("\n", $kept);
        $text = preg_replace('/<p>\s*[^<]*[:：]\s*<\/p>/u', '', $text) ?? $text;
        $text = preg_replace('/<p>\s*<\/p>/u', '', $text) ?? $text;
        $text = preg_replace('/<p>\s*<strong>\s*<\/strong>\s*<\/p>/u', '', $text) ?? $text;
        // Drop orphan headings with no following content block
        $text = preg_replace('/<h[23]>[^<]*<\/h[23]>(?=\s*(?:<h[23]>|$))/u', '', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
        $text = preg_replace('/>\s+</u', '><', $text) ?? $text;

        return trim($text);
    }

    public function section(string $heading, ?string $value, string $fallback = ''): string
    {
        $value = $this->cleanMultiline($value);
        if ($value === '') {
            return $fallback !== ''
                ? '<h3>'.$this->e($heading).'</h3><p>'.$this->e($fallback).'</p>'
                : '';
        }

        return '<h3>'.$this->e($heading).'</h3><p>'.nl2br($this->e($value), false).'</p>';
    }

    public function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function clean(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $value = strip_tags($value);
        // Strip leftover event-handler-looking fragments and zero-width chars
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value) ?? $value;
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value;
    }

    protected function cleanMultiline(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $value = strip_tags($value);
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value) ?? $value;
        $value = preg_replace("/[ \t]+/u", ' ', $value) ?? $value;

        return trim($value);
    }

    protected function provincesList(JobPost $job): string
    {
        $list = is_array($job->provinces) ? $job->provinces : [];
        $list = array_values(array_filter(array_map(fn ($p) => $this->clean(is_string($p) ? $p : null), $list)));

        return $list !== [] ? implode('، ', $list) : '';
    }

    protected function faDate(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        try {
            $carbon = $date instanceof \Carbon\CarbonInterface
                ? $date
                : \Illuminate\Support\Carbon::parse((string) $date);

            return $carbon->timezone(config('content.timezone', 'Asia/Tehran'))
                ->locale('fa')
                ->translatedFormat('Y/m/d');
        } catch (\Throwable) {
            return '';
        }
    }

    protected function safeUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        if (! preg_match('#^https?://#i', $url)) {
            return '';
        }
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return '';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }
        $host = $parts['host'] ?? null;
        if (! is_string($host) || $host === '') {
            return '';
        }
        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        return Str::limit($url, 500, '');
    }
}
