<?php

namespace App\Services\Content;

use App\Enums\Aggregation\JobSourceReliability;
use App\Enums\Content\ContentType;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Models\JobSource;

class ContentQualityService
{
    /**
     * @return array{valid: bool, errors: list<string>}
     */
    public function validate(GeneratedContent $content, ?JobPost $job = null): array
    {
        $errors = [];
        $min = (int) config('content.minimum_content_length', 280);

        if (! filled($content->title) || mb_strlen(trim($content->title)) < 8) {
            $errors[] = 'عنوان معتبر نیست.';
        }
        if (! filled($content->content)) {
            $errors[] = 'متن محتوا خالی است.';
        } elseif (mb_strlen(strip_tags($content->content)) < $min) {
            $errors[] = "طول محتوا کمتر از حداقل ({$min}) است.";
        }
        if (preg_match('/\{[a-z0-9_]+\}/u', $content->title.' '.$content->content)) {
            $errors[] = 'متغیرهای قالب جایگزین‌نشده باقی مانده‌اند.';
        }
        if ($this->hasDuplicateParagraphs($content->content ?? '')) {
            $errors[] = 'پاراگراف‌های تکراری تشخیص داده شد.';
        }
        if (! mb_check_encoding(($content->title ?? '').($content->content ?? ''), 'UTF-8')) {
            $errors[] = 'رمزگذاری UTF-8 نامعتبر است.';
        }
        if ($this->containsDangerousHtml($content->content ?? '')) {
            $errors[] = 'محتوای HTML ناامن تشخیص داده شد.';
        }

        if (preg_match_all('#https?://[^\s"\'<>]+#iu', $content->content ?? '', $m)) {
            foreach ($m[0] as $url) {
                if (! $this->isHttpUrl($url)) {
                    $errors[] = 'آدرس نامعتبر در محتوا وجود دارد.';
                    break;
                }
            }
        }
        if (preg_match('/(?:javascript|data|vbscript|file):/i', $content->content ?? '')) {
            $errors[] = 'طرح URL ناامن در محتوا وجود دارد.';
        }

        $job ??= $content->jobPost;
        if ($content->job_post_id && ! $job) {
            $errors[] = 'آگهی مرتبط یافت نشد.';
        }
        if ($job) {
            if ($job->status !== 'approved') {
                $errors[] = 'فقط آگهی‌های تأییدشده مجاز هستند.';
            }
            if (! filled($job->company_name) || ! filled($job->title)) {
                $errors[] = 'عنوان و سازمان آگهی برای تولید محتوا الزامی است.';
            }
            if (! $this->hasEnoughFactualFields($job, $content->content_type instanceof ContentType ? $content->content_type : null)) {
                $errors[] = 'دادهٔ واقعی کافی برای تولید مقالهٔ مفید وجود ندارد.';
            }
            $source = $job->relationLoaded('source') ? $job->source : JobSource::query()->find($job->job_source_id);
            if (! $source instanceof JobSource) {
                $errors[] = 'منبع آگهی مشخص نیست.';
            } elseif (! $this->sourceAllowed($source)) {
                $errors[] = 'منبع آگهی برای تولید خودکار تأیید نشده است.';
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    public function sourceAllowed(JobSource $source): bool
    {
        if (! $source->is_enabled || ! $source->is_approved) {
            return false;
        }

        $allowed = config('content.allowed_source_reliability', ['official', 'highly_trusted', 'trusted']);
        $level = $source->reliability_level instanceof JobSourceReliability
            ? $source->reliability_level->value
            : (string) $source->reliability_level;

        return in_array($level, $allowed, true);
    }

    /**
     * Thin-content guard: require enough verified fields for a useful article.
     */
    public function hasEnoughFactualFields(JobPost $job, ?ContentType $type = null): bool
    {
        $score = 0;
        if (filled($job->registration_deadline)) {
            $score += 2;
        }
        if (filled($job->registration_starts_at)) {
            $score += 1;
        }
        if (filled($job->exam_date)) {
            $score += 2;
        }
        if (filled($job->description) && mb_strlen(strip_tags((string) $job->description)) >= 40) {
            $score += 2;
        }
        if (filled($job->requirements) && mb_strlen(strip_tags((string) $job->requirements)) >= 20) {
            $score += 2;
        }
        if (filled($job->education) || filled($job->field_of_study)) {
            $score += 1;
        }
        if (filled($job->registration_link) || filled($job->source_url)) {
            $score += 1;
        }
        if (filled($job->province) || (is_array($job->provinces) && $job->provinces !== [])) {
            $score += 1;
        }

        $min = (int) config('content.minimum_factual_score', 3);
        if ($type === ContentType::RegistrationDeadline) {
            return filled($job->registration_deadline) && filled($job->company_name);
        }
        if ($type === ContentType::AgeRequirements || $type === ContentType::RequiredDocuments) {
            return filled($job->requirements) && mb_strlen(strip_tags((string) $job->requirements)) >= 20;
        }

        return $score >= $min;
    }

    protected function containsDangerousHtml(string $html): bool
    {
        return (bool) preg_match('/<\s*(script|iframe|object|embed|svg|math|link|meta|base|form)\b/i', $html)
            || (bool) preg_match('/\son[a-z]+\s*=/i', $html)
            || (bool) preg_match('/<(?:img|a|source)\b[^>]+(?:href|src)\s*=\s*[\'"]?\s*(?:javascript|data|vbscript):/i', $html);
    }

    protected function hasDuplicateParagraphs(string $html): bool
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        $parts = preg_split('/[.!?؟。]\s+/u', $text) ?: [];
        $seen = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if (mb_strlen($p) < 40) {
                continue;
            }
            $key = mb_strtolower($p);
            if (isset($seen[$key])) {
                return true;
            }
            $seen[$key] = true;
        }

        return false;
    }

    protected function isHttpUrl(string $url): bool
    {
        if (! preg_match('#^https?://#i', $url)) {
            return false;
        }
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '';
    }
}
