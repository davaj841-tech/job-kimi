<?php

namespace App\Console\Commands;

use App\Jobs\Seo\OptimizeLowScoreSeoJob;
use App\Jobs\Seo\PingSitemapJob;
use App\Jobs\Seo\RunSeoAuditJob;
use App\Services\Seo\SeoAutoOptimizer;
use App\Services\Seo\SeoManager;
use App\Services\Seo\SitemapService;
use Illuminate\Console\Command;

class SeoOptimizeAllCommand extends Command
{
    protected $signature = 'seo:optimize-all
                            {--low-score : Only optimize pages below min score threshold}
                            {--ping : Ping search engines after sitemap refresh}
                            {--audit : Run full SEO audit after optimization}';

    protected $description = 'Auto-optimize SEO meta for all content and refresh sitemap';

    public function handle(
        SeoAutoOptimizer $optimizer,
        SeoManager $seoManager,
        SitemapService $sitemapService,
    ): int {
        if ($this->option('low-score')) {
            OptimizeLowScoreSeoJob::dispatchSync();
            $this->info('Low-score pages optimized.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach (config('seo.seoable_models', []) as $modelClass) {
            $modelClass::query()->chunkById(100, function ($models) use ($optimizer, $seoManager, &$count) {
                foreach ($models as $model) {
                    if ($optimizer->optimize($model)) {
                        $seoManager->analyze($model);
                        $count++;
                    }
                }
            });
        }

        $sitemapService->clearCache();
        $this->info("Optimized {$count} page(s). Sitemap cache cleared.");

        if ($this->option('ping')) {
            PingSitemapJob::dispatchSync();
            $this->info('Search engines pinged.');
        }

        if ($this->option('audit')) {
            RunSeoAuditJob::dispatchSync('full');
            $this->info('Full SEO audit completed.');
        }

        return self::SUCCESS;
    }
}
