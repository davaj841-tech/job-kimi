<?php

namespace App\Services\Seo;

use App\Models\Seo\SeoAnalysis;
use App\Models\Seo\SeoKeyword;
use App\Models\Seo\SeoMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeoAnalyzer
{
    public function analyze(Model $model): SeoAnalysis
    {
        $checks = $this->runChecks($model);
        $score = $this->calculateScore($checks);
        $status = $this->determineStatus($score);

        $payload = [
            'score' => $score,
            'status' => $status,
            'checks' => $checks,
            'analyzed_at' => now(),
        ];

        if (method_exists($model, 'seoAnalysis')) {
            return $model->seoAnalysis()->updateOrCreate(
                ['analyzable_type' => $model->getMorphClass(), 'analyzable_id' => $model->getKey()],
                $payload
            );
        }

        return SeoAnalysis::query()->updateOrCreate(
            ['analyzable_type' => $model->getMorphClass(), 'analyzable_id' => $model->getKey()],
            $payload
        );
    }

    /**
     * @return array<string, array{pass: bool, value: mixed, message: string}>
     */
    protected function runChecks(Model $model): array
    {
        $title = $this->getTitle($model);
        $description = $this->getDescription($model);
        $content = $this->getContent($model);

        $keyword = $model->getRelationValue('seoKeyword');
        $keyword = $keyword instanceof SeoKeyword ? $keyword : null;
        $focusKeyword = $keyword?->focus_keyword;

        return [
            'title' => $this->checkTitle($title),
            'description' => $this->checkDescription($description),
            'h1' => $this->checkH1($content),
            'keyword_in_title' => $this->checkKeywordInField($focusKeyword, $title),
            'keyword_in_description' => $this->checkKeywordInField($focusKeyword, $description),
            'keyword_in_content' => $this->checkKeywordInContent($focusKeyword, $content),
            'content_length' => $this->checkContentLength($content),
            'images' => $this->checkImages($content),
            'internal_links' => $this->checkInternalLinks($content),
            'schema' => $this->checkSchema($model),
            'canonical' => $this->checkCanonical($model),
        ];
    }

    /**
     * @return array{pass: bool, value: mixed, message: string}
     */
    protected function checkTitle(?string $title): array
    {
        $len = mb_strlen($title ?? '');
        $pass = $len >= 10 && $len <= 60;

        return ['pass' => $pass, 'value' => $len, 'message' => $pass ? 'طول عنوان مناسب است' : 'عنوان باید بین ۱۰ تا ۶۰ کاراکتر باشد'];
    }

    /**
     * @return array{pass: bool, value: mixed, message: string}
     */
    protected function checkDescription(?string $desc): array
    {
        $len = mb_strlen($desc ?? '');
        $pass = $len >= 50 && $len <= 160;

        return ['pass' => $pass, 'value' => $len, 'message' => $pass ? 'توضیحات متا مناسب است' : 'توضیحات متا باید بین ۵۰ تا ۱۶۰ کاراکتر باشد'];
    }

    /**
     * @return array{pass: bool, value: mixed, message: string}
     */
    protected function checkH1(?string $content): array
    {
        $count = preg_match_all('/<h1[^>]*>/i', $content ?? '');
        $pass = $count === 1;

        return ['pass' => $pass, 'value' => $count, 'message' => $pass ? 'یک H1 وجود دارد' : 'صفحه باید دقیقاً یک H1 داشته باشد'];
    }

    /**
     * @return array{pass: bool, value: mixed, message: string}
     */
    protected function checkKeywordInField(?string $keyword, ?string $field): array
    {
        if (! $keyword) {
            return ['pass' => false, 'value' => null, 'message' => 'کلمه کلیدی تعریف نشده'];
        }
        $pass = Str::contains(mb_strtolower($field ?? ''), mb_strtolower($keyword));

        return ['pass' => $pass, 'value' => $keyword, 'message' => $pass ? 'کلمه کلیدی موجود است' : 'کلمه کلیدی یافت نشد'];
    }

    /**
     * @return array{pass: bool, value: mixed, message: string}
     */
    protected function checkKeywordInContent(?string $keyword, ?string $content): array
    {
        if (! $keyword || ! $content) {
            return ['pass' => false, 'value' => 0, 'message' => 'کلمه کلیدی یا محتوا خالی است'];
        }

        $text = strip_tags($content);
        $wordCount = str_word_count($text, 0, 'آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهیئ');
        $keywordCount = mb_substr_count(mb_strtolower($text), mb_strtolower($keyword));
        $density = $wordCount > 0 ? ($keywordCount / $wordCount) * 100 : 0;

        $min = config('seo.keyword_density.min', 0.5);
        $max = config('seo.keyword_density.max', 3.0);
        $pass = $density >= $min && $density <= $max;

        $message = $pass ? 'تراکم کلمه کلیدی مناسب است' : ($density > $max ? 'تراکم بیش از حد (Keyword Stuffing)' : 'تراکم کلمه کلیدی کم است');

        return ['pass' => $pass, 'value' => round($density, 2), 'message' => $message];
    }

    /**
     * @return array{pass: bool, value: mixed, message: string}
     */
    protected function checkContentLength(?string $content): array
    {
        $text = strip_tags($content ?? '');
        $count = str_word_count($text, 0, 'آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهیئ');
        $min = config('seo.content_length.min', 300);
        $pass = $count >= $min;

        return ['pass' => $pass, 'value' => $count, 'message' => $pass ? 'طول محتوا کافی است' : "محتوا باید حداقل {$min} کلمه باشد"];
    }

    /**
     * @return array{pass: bool, value: mixed, message: string}
     */
    protected function checkImages(?string $content): array
    {
        $imgs = preg_match_all('/<img[^>]+>/i', $content ?? '');
        $alts = preg_match_all('/<img[^>]+alt\s*=\s*"[^"]+"/i', $content ?? '');
        $pass = $imgs === 0 || $alts === $imgs;

        return ['pass' => $pass, 'value' => ['total' => $imgs, 'with_alt' => $alts], 'message' => $pass ? 'تصاویر alt دارند' : 'برخی تصاویر alt ندارند'];
    }

    /**
     * @return array{pass: bool, value: mixed, message: string}
     */
    protected function checkInternalLinks(?string $content): array
    {
        $appUrl = config('app.url');
        preg_match_all('/href\s*=\s*"([^"]+)"/i', $content ?? '', $matches);
        $urls = $matches[1];
        $internal = collect($urls)->filter(fn ($u) => Str::startsWith($u, [$appUrl, '/']))->count();
        $pass = $internal >= 2;

        return ['pass' => $pass, 'value' => $internal, 'message' => $pass ? 'لینک داخلی کافی' : 'حداقل ۲ لینک داخلی پیشنهاد می‌شود'];
    }

    /**
     * @return array{pass: bool, value: mixed, message: string}
     */
    protected function checkSchema(Model $model): array
    {
        $has = (method_exists($model, 'seoFaqs') && $model->seoFaqs()->exists())
            || method_exists($model, 'getSeoSchemaType');

        return ['pass' => $has, 'value' => $has, 'message' => $has ? 'Schema تعریف شده' : 'Schema تعریف نشده'];
    }

    /**
     * @return array{pass: bool, value: mixed, message: string}
     */
    protected function checkCanonical(Model $model): array
    {
        $metaRaw = $model->getRelationValue('seoMeta');
        $meta = $metaRaw instanceof SeoMeta ? $metaRaw : null;
        $has = filled($meta !== null ? $meta->canonical : null);

        return ['pass' => true, 'value' => $has, 'message' => 'Canonical بررسی شد'];
    }

    /**
     * @param  array<string, array{pass: bool, value: mixed, message: string}>  $checks
     */
    protected function calculateScore(array $checks): int
    {
        $weights = config('seo.score_weights', []);
        $total = 0;
        $earned = 0;

        foreach ($checks as $key => $check) {
            $weight = $weights[$key] ?? 5;
            $total += $weight;
            if ($check['pass'] ?? false) {
                $earned += $weight;
            }
        }

        return $total > 0 ? (int) round(($earned / $total) * 100) : 0;
    }

    protected function determineStatus(int $score): string
    {
        return match (true) {
            $score >= 90 => 'excellent',
            $score >= 75 => 'good',
            $score >= 50 => 'needs_improvement',
            default => 'poor',
        };
    }

    protected function getTitle(Model $model): ?string
    {
        $metaRaw = $model->getRelationValue('seoMeta');
        $meta = $metaRaw instanceof SeoMeta ? $metaRaw : null;
        $value = ($meta !== null ? $meta->title : null)
            ?? $model->getAttribute('meta_title')
            ?? $model->getAttribute('title');

        return $value !== null ? (string) $value : null;
    }

    protected function getDescription(Model $model): ?string
    {
        $metaRaw = $model->getRelationValue('seoMeta');
        $meta = $metaRaw instanceof SeoMeta ? $metaRaw : null;
        $value = ($meta !== null ? $meta->description : null)
            ?? $model->getAttribute('meta_description')
            ?? $model->getAttribute('excerpt')
            ?? $model->getAttribute('description');

        return $value !== null ? (string) $value : null;
    }

    protected function getContent(Model $model): ?string
    {
        $value = $model->getAttribute('content') ?? $model->getAttribute('description');

        return $value !== null ? (string) $value : null;
    }
}
