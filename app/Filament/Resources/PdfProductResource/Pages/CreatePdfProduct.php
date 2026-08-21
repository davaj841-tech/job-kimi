<?php

namespace App\Filament\Resources\PdfProductResource\Pages;

use App\Filament\Concerns\InteractsWithSeoForm;
use App\Filament\Resources\PdfProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePdfProduct extends CreateRecord
{
    use InteractsWithSeoForm;

    protected static string $resource = PdfProductResource::class;
}
