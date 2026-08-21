<?php

namespace App\Filament\Resources\BlogPostResource\Pages;

use App\Filament\Concerns\InteractsWithSeoForm;
use App\Filament\Resources\BlogPostResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlogPost extends CreateRecord
{
    use InteractsWithSeoForm;

    protected static string $resource = BlogPostResource::class;
}
