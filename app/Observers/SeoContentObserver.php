<?php

namespace App\Observers;

use App\Jobs\Seo\AnalyzeSeoJob;
use App\Jobs\Seo\AutoOptimizeSeoJob;
use App\Services\Seo\SeoAutomationService;
use App\Traits\HasSeo;
use Illuminate\Database\Eloquent\Model;

class SeoContentObserver
{
    /** @var array<int, string> */
    protected array $watchFields = [
        'title',
        'name',
        'description',
        'content',
        'excerpt',
        'meta_title',
        'meta_description',
        'status',
        'is_published',
        'published_at',
        'is_active',
    ];

    public function __construct(protected SeoAutomationService $automation) {}

    public function created(Model $model): void
    {
        if ($this->isEligible($model) && config('seo.automation.auto_optimize_on_create', true)) {
            AutoOptimizeSeoJob::dispatch($model);
        } else {
            $this->dispatchAnalysis($model);
        }

        $this->automation->contentChanged();
    }

    public function updated(Model $model): void
    {
        if ($model->wasChanged($this->watchFields)) {
            $this->dispatchAnalysis($model);
            $this->automation->contentChanged();
        }
    }

    protected function dispatchAnalysis(Model $model): void
    {
        if (! $this->isEligible($model)) {
            return;
        }

        AnalyzeSeoJob::dispatch($model);
    }

    protected function isEligible(Model $model): bool
    {
        if (! in_array(HasSeo::class, class_uses_recursive($model), true)) {
            return false;
        }

        return in_array($model::class, config('seo.analyze_on_change_models', []), true);
    }
}
