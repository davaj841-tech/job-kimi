<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithStaffAccess;
use App\Filament\Resources\ExamCategoryResource\Pages;
use App\Models\ExamCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExamCategoryResource extends Resource
{
    use InteractsWithStaffAccess;

    protected static ?string $model = ExamCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'طبقه‌بندی آزمون';

    protected static ?string $modelLabel = 'طبقه‌بندی آزمون';

    protected static ?string $pluralModelLabel = 'طبقه‌بندی‌های آزمون';

    protected static ?string $navigationGroup = 'آزمون‌ها';

    public static function canViewAny(): bool
    {
        return self::staffAdminOnly();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('نام')->required()->helperText('نام طبقه‌بندی آزمون'),
            Forms\Components\TextInput::make('slug')->label('اسلاگ')->helperText('شناسه URL — در صورت خالی بودن خودکار ساخته می‌شود'),
            Forms\Components\TextInput::make('icon')->label('آیکون')->helperText('نام آیکون یا مسیر تصویر'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('نام')->searchable(),
                Tables\Columns\TextColumn::make('slug')->label('اسلاگ'),
                Tables\Columns\TextColumn::make('icon')->label('آیکون'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('ویرایش'),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    self::secureDeleteBulkAction(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExamCategorys::route('/'),
            'create' => Pages\CreateExamCategory::route('/create'),
            'edit' => Pages\EditExamCategory::route('/{record}/edit'),
        ];
    }
}
