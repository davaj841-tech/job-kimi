<?php

namespace App\Filament\Resources\FeatureResource\Pages;

use App\Filament\Resources\FeatureResource;
use App\Services\FeatureFlagService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFeature extends EditRecord
{
    protected static string $resource = FeatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('حذف')
                ->after(fn () => app(FeatureFlagService::class)->forgetCache()),
        ];
    }

    protected function afterSave(): void
    {
        app(FeatureFlagService::class)->forgetCache();
    }
}
