<?php

namespace App\Filament\Resources\PdfProductResource\Pages;

use App\Filament\Resources\PdfProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPdfProducts extends ListRecords
{
    protected static string $resource = PdfProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('ایجاد'),
        ];
    }
}
