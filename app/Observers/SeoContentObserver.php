<?php

namespace App\Observers;

use App\Jobs\Seo\AnalyzeSeoJob;
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

    public function created(Model $model): void
    {
        $this->dispatchIfEligible($model);
    }

    public function updated(Model $model): void
    {
        if ($model->wasChanged($this->watchFields)) {
            $this->dispatchIfEligible($model);
        }
    }

    protected function dispatchIfEligible(Model $model): void
    {
        if (! in_array(HasSeo::class, class_uses_recursive($model), true)) {
            return;
        }

        if (! in_array($model::class, config('seo.analyze_on_change_models', []), true)) {
            return;
        }

        AnalyzeSeoJob::dispatch($model);
    }
}
