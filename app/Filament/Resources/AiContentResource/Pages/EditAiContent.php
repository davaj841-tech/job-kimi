<?php

namespace App\Filament\Resources\AiContentResource\Pages;

use App\Filament\Resources\AiContentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAiContent extends EditRecord
{
    protected static string $resource = AiContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('حذف'),
        ];
    }
}
