<?php

namespace App\Filament\Resources\JobPostResource\Pages;

use App\Filament\Concerns\InteractsWithSeoForm;
use App\Filament\Resources\JobPostResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJobPost extends CreateRecord
{
    use InteractsWithSeoForm;

    protected static string $resource = JobPostResource::class;
}
