<?php

namespace App\Services\Seo;

use App\Jobs\Seo\PingSitemapJob;
use Illuminate\Support\Facades\Cache;

class SeoAutomationService
{
    public function __construct(
        protected SitemapService $sitemapService,
    ) {}

    public function contentChanged(): void
    {
        if (config('seo.automation.sitemap_invalidate_on_change', true)) {
            $this->sitemapService->clearCache();
        }

        if (! config('seo.automation.ping_search_engines', true)) {
            return;
        }

        $debounce = max(60, (int) config('seo.automation.ping_debounce_seconds', 300));
        $lock = Cache::lock('seo:sitemap-ping', $debounce);

        if ($lock->get()) {
            PingSitemapJob::dispatch()->delay(now()->addSeconds(min(30, $debounce)));
        }
    }
}
