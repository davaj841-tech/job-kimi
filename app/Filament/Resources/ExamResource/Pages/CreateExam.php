<?php

namespace App\Filament\Resources\ExamResource\Pages;

use App\Filament\Concerns\InteractsWithSeoForm;
use App\Filament\Resources\ExamResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExam extends CreateRecord
{
    use InteractsWithSeoForm;

    protected static string $resource = ExamResource::class;
}
