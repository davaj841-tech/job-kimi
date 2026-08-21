<?php

namespace App\Filament\Resources\Seo\SeoRedirectResource\Pages;

use App\Filament\Resources\Seo\SeoRedirectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSeoRedirects extends ListRecords
{
    protected static string $resource = SeoRedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
