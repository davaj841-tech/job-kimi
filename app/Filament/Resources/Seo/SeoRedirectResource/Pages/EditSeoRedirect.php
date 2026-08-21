<?php

namespace App\Filament\Resources\Seo\SeoRedirectResource\Pages;

use App\Filament\Resources\Seo\SeoRedirectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSeoRedirect extends EditRecord
{
    protected static string $resource = SeoRedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
