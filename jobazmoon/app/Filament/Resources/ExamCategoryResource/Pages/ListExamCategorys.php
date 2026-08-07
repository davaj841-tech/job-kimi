<?php

namespace App\Filament\Resources\ExamCategoryResource\Pages;

use App\Filament\Resources\ExamCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExamCategorys extends ListRecords
{
    protected static string $resource = ExamCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('ایجاد'),
        ];
    }
}