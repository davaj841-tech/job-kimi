<?php

namespace App\Filament\Resources\JobPostResource\Pages;

use App\Filament\Concerns\InteractsWithSeoForm;
use App\Filament\Resources\JobPostResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJobPost extends EditRecord
{
    use InteractsWithSeoForm;

    protected static string $resource = JobPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('حذف'),
        ];
    }
}
