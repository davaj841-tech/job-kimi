<?php

namespace App\Filament\Resources\PdfProductResource\Pages;

use App\Filament\Resources\PdfProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePdfProduct extends CreateRecord
{
    protected static string $resource = PdfProductResource::class;
}
