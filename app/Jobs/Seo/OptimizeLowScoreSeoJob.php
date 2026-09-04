<?php

namespace App\Jobs\Seo;

use App\Models\Seo\SeoAnalysis;
use App\Services\Seo\SeoAutoOptimizer;
use App\Services\Seo\SeoManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OptimizeLowScoreSeoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function handle(SeoAutoOptimizer $optimizer, SeoManager $seoManager): void
    {
        $minScore = (int) config('seo.automation.auto_optimize_min_score', 75);
        $optimized = 0;

        foreach (config('seo.seoable_models', []) as $modelClass) {
            $analyses = SeoAnalysis::query()
                ->where('analyzable_type', (new $modelClass)->getMorphClass())
                ->where('score', '<', $minScore)
                ->limit(50)
                ->get();

            foreach ($analyses as $analysis) {
                $model = $modelClass::query()->find($analysis->analyzable_id);
                if (! $model) {
                    continue;
                }

                if ($optimizer->optimize($model)) {
                    $seoManager->analyze($model);
                    $optimized++;
                }
            }
        }
    }
}
