<?php

namespace App\Jobs\Seo;

use App\Services\Seo\InternalLinkExtractor;
use App\Services\Seo\SeoAutoOptimizer;
use App\Services\Seo\SeoManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoOptimizeSeoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(protected Model $model) {}

    public function handle(
        SeoAutoOptimizer $optimizer,
        SeoManager $seoManager,
        InternalLinkExtractor $linkExtractor,
    ): void {
        if (! config('seo.automation.auto_optimize_on_create', true)) {
            return;
        }

        if (! $this->model->exists) {
            return;
        }

        $optimizer->optimize($this->model);
        $linkExtractor->extract($this->model);
        $seoManager->analyze($this->model);
    }
}
