<?php

namespace App\Filament\Concerns;

use App\Services\Seo\SeoManager;

trait InteractsWithSeoForm
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        $record = $this->getRecord();
        if (! $record) {
            return $data;
        }

        $record->loadMissing(['seoMeta', 'seoKeyword', 'seoAnalysis']);

        $data['seo_title'] = $record->seoMeta?->title;
        $data['seo_description'] = $record->seoMeta?->description;
        $data['seo_canonical'] = $record->seoMeta?->canonical;
        $data['seo_robots'] = $record->seoMeta?->robots ?? 'index, follow';
        $data['seo_og_image'] = $record->seoMeta?->og_image;
        $data['seo_twitter_image'] = $record->seoMeta?->og_image;
        $data['seo_focus_keyword'] = $record->seoKeyword?->focus_keyword;

        return $data;
    }

    protected function afterCreate(): void
    {
        parent::afterCreate();
        $this->persistSeoForm();
    }

    protected function afterSave(): void
    {
        parent::afterSave();
        $this->persistSeoForm();
    }

    protected function persistSeoForm(): void
    {
        $state = $this->form->getState();
        $record = $this->record;

        $hasSeoInput = collect([
            $state['seo_title'] ?? null,
            $state['seo_description'] ?? null,
            $state['seo_canonical'] ?? null,
            $state['seo_robots'] ?? null,
            $state['seo_og_image'] ?? null,
            $state['seo_twitter_image'] ?? null,
        ])->filter(fn ($v) => filled($v))->isNotEmpty();

        $manager = app(SeoManager::class);

        if ($hasSeoInput) {
            $manager->updateMeta($record, array_filter([
                'title' => $state['seo_title'] ?? null,
                'description' => $state['seo_description'] ?? null,
                'canonical' => $state['seo_canonical'] ?? null,
                'robots' => $state['seo_robots'] ?? 'index, follow',
                'og_image' => $state['seo_og_image'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''));
        }

        if (filled($state['seo_focus_keyword'] ?? null)) {
            $manager->updateKeyword($record, [
                'focus_keyword' => $state['seo_focus_keyword'],
            ]);
        }
    }
}
