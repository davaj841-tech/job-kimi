<?php

namespace App\Filament\Resources\ExamResource\Pages;

use App\Filament\Concerns\InteractsWithSeoForm;
use App\Filament\Resources\ExamResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExam extends EditRecord
{
    use InteractsWithSeoForm;

    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('حذف'),
        ];
    }
}
