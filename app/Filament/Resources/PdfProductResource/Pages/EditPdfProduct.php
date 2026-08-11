<?php

namespace App\Filament\Resources\PdfProductResource\Pages;

use App\Filament\Resources\PdfProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPdfProduct extends EditRecord
{
    protected static string $resource = PdfProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('حذف'),
        ];
    }
}
