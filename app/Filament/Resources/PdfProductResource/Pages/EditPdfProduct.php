<?php

namespace App\Filament\Resources\PdfProductResource\Pages;

use App\Filament\Concerns\InteractsWithSeoForm;
use App\Filament\Resources\PdfProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPdfProduct extends EditRecord
{
    use InteractsWithSeoForm;

    protected static string $resource = PdfProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('حذف'),
        ];
    }
}
