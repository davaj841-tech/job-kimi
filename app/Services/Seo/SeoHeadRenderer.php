<?php

namespace App\Services\Seo;

class SeoHeadRenderer
{
    /**
     * @param  array{meta?: array<string, mixed>, schemas?: list<array<string, mixed>>, schema?: mixed}|null  $payload
     */
    public function render(?array $payload): string
    {
        if (! $payload || empty($payload['meta'])) {
            return '';
        }

        $meta = $payload['meta'];
        $html = [];

        $title = e((string) ($meta['meta_title'] ?? $meta['title'] ?? ''));
        if ($title !== '') {
            $html[] = "<title>{$title}</title>";
        }

        $description = $meta['meta_description'] ?? $meta['description'] ?? null;
        if ($description) {
            $html[] = '<meta name="description" content="'.e(mb_substr(strip_tags((string) $description), 0, 320)).'">';
        }

        if (! empty($meta['robots'])) {
            $html[] = '<meta name="robots" content="'.e((string) $meta['robots']).'">';
        }

        if (! empty($meta['focus_keyword'])) {
            $html[] = '<meta name="keywords" content="'.e((string) $meta['focus_keyword']).'">';
        }

        $canonical = $meta['canonical_url'] ?? $meta['canonical'] ?? null;
        if ($canonical) {
            $html[] = '<link rel="canonical" href="'.e((string) $canonical).'">';
        }

        $ogTitle = $meta['og_title'] ?? $title;
        $ogDescription = $meta['og_description'] ?? $description;
        $ogImage = $meta['og_image'] ?? null;
        $ogType = $meta['og_type'] ?? 'website';

        if ($ogTitle) {
            $html[] = '<meta property="og:title" content="'.e((string) $ogTitle).'">';
        }
        if ($ogDescription) {
            $html[] = '<meta property="og:description" content="'.e(mb_substr(strip_tags((string) $ogDescription), 0, 200)).'">';
        }
        if ($ogImage) {
            $html[] = '<meta property="og:image" content="'.e((string) $ogImage).'">';
        }
        if ($canonical) {
            $html[] = '<meta property="og:url" content="'.e((string) $canonical).'">';
        }
        $html[] = '<meta property="og:type" content="'.e((string) $ogType).'">';
        $html[] = '<meta property="og:site_name" content="'.e((string) config('seo.site_name')).'">';
        $html[] = '<meta property="og:locale" content="fa_IR">';

        $twitterTitle = $meta['twitter_title'] ?? $ogTitle;
        $twitterDescription = $meta['twitter_description'] ?? $ogDescription;
        $twitterImage = $meta['twitter_image'] ?? $ogImage;
        $twitterCard = $meta['twitter_card'] ?? ($ogImage ? 'summary_large_image' : 'summary');

        if ($twitterTitle) {
            $html[] = '<meta name="twitter:title" content="'.e((string) $twitterTitle).'">';
        }
        if ($twitterDescription) {
            $html[] = '<meta name="twitter:description" content="'.e(mb_substr(strip_tags((string) $twitterDescription), 0, 200)).'">';
        }
        if ($twitterImage) {
            $html[] = '<meta name="twitter:image" content="'.e((string) $twitterImage).'">';
        }
        $html[] = '<meta name="twitter:card" content="'.e((string) $twitterCard).'">';

        $schemas = $payload['schemas'] ?? [];
        if (empty($schemas) && ! empty($payload['schema'])) {
            $schemas = is_array($payload['schema']) && array_is_list($payload['schema'])
                ? $payload['schema']
                : [$payload['schema']];
        }

        foreach ($schemas as $index => $schema) {
            if (! is_array($schema)) {
                continue;
            }
            $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $html[] = '<script type="application/ld+json" id="jsonld-ssr-'.$index.'">'.$json.'</script>';
        }

        return implode("\n    ", $html);
    }
}
