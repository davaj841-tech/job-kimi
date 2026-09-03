<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SearchEnginePingService
{
    /**
     * @return array<string, bool>
     */
    public function pingSitemap(?string $sitemapUrl = null): array
    {
        $sitemapUrl = $sitemapUrl ?? url('/sitemap.xml');
        $encoded = urlencode($sitemapUrl);
        $results = [];

        foreach ($this->endpoints() as $engine => $template) {
            try {
                $response = Http::timeout(10)->get(str_replace('{url}', $encoded, $template));
                $results[$engine] = $response->successful();
            } catch (\Throwable $e) {
                Log::warning("SEO ping failed for {$engine}: {$e->getMessage()}");
                $results[$engine] = false;
            }
        }

        return $results;
    }

    /**
     * @return array<string, string>
     */
    protected function endpoints(): array
    {
        $configured = config('seo.automation.ping_endpoints', []);

        if ($configured !== []) {
            return $configured;
        }

        return [
            'google' => 'https://www.google.com/ping?sitemap={url}',
            'bing' => 'https://www.bing.com/ping?sitemap={url}',
        ];
    }
}
