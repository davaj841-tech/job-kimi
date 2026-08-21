<?php

namespace App\Filament\Resources\BlogPostResource\Pages;

use App\Filament\Concerns\InteractsWithSeoForm;
use App\Filament\Resources\BlogPostResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlogPost extends EditRecord
{
    use InteractsWithSeoForm;

    protected static string $resource = BlogPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('حذف'),
        ];
    }
}
