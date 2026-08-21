<?php

namespace App\Jobs\Seo;

use App\Models\Seo\SeoAudit;
use App\Services\Seo\CannibalizationService;
use App\Services\Seo\DuplicateContentService;
use App\Services\Seo\SeoAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunSeoAuditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(protected string $type = 'full') {}

    public function handle(SeoAnalyzer $analyzer, CannibalizationService $cannibalization, DuplicateContentService $duplicate): void
    {
        $audit = SeoAudit::create([
            'type' => $this->type,
            'started_at' => now(),
        ]);

        $issues = 0;
        $checked = 0;
        $results = [];

        if ($this->type === 'full' || $this->type === 'cannibalization') {
            $cannibalizations = $cannibalization->findCannibalization();
            $issues += count($cannibalizations);
            $results['cannibalization'] = $cannibalizations;
        }

        if ($this->type === 'full' || $this->type === 'duplicate') {
            $duplicates = $duplicate->auditBatch();
            $issues += count($duplicates);
            $results['duplicate_content'] = $duplicates;
        }

        if ($this->type === 'full') {
            foreach (config('seo.seoable_models', []) as $modelClass) {
                $models = $modelClass::query()->limit(200)->get();
                foreach ($models as $model) {
                    $analyzer->analyze($model);
                    $checked++;
                }
            }
        }

        $audit->update([
            'results' => $results,
            'pages_checked' => $checked,
            'issues_found' => $issues,
            'completed_at' => now(),
        ]);
    }
}
