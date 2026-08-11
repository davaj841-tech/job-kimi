<?php

namespace App\Filament\Resources\FeatureResource\Pages;

use App\Filament\Resources\FeatureResource;
use App\Services\FeatureFlagService;
use Filament\Resources\Pages\CreateRecord;

class CreateFeature extends CreateRecord
{
    protected static string $resource = FeatureResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        app(FeatureFlagService::class)->forgetCache();
    }
}
