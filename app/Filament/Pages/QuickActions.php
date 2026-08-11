<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Page;

class QuickActions extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'عملیات سریع';

    protected static ?string $title = 'عملیات سریع';

    protected static ?string $navigationGroup = 'تنظیمات';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.quick-actions';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_exam')
                ->label('آزمون جدید')
                ->icon('heroicon-o-plus')
                ->url(route('filament.admin.resources.exams.create')),
            Action::make('create_user')
                ->label('کاربر جدید')
                ->icon('heroicon-o-user-plus')
                ->url(route('filament.admin.resources.users.create')),
            Action::make('vue_admin')
                ->label('پنل Vue')
                ->icon('heroicon-o-computer-desktop')
                ->url('/admin')
                ->color('gray'),
        ];
    }
}
