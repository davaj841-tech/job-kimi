<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PdfProductResource\Pages;
use App\Models\PdfProduct;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PdfProductResource extends Resource
{
    protected static ?string $model = PdfProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-document';

    protected static ?string $navigationLabel = 'محصولات PDF';

    protected static ?string $modelLabel = 'محصول PDF';

    protected static ?string $pluralModelLabel = 'محصولات PDF';

    protected static ?string $navigationGroup = 'محتوا';

    

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('عنوان')->required()->helperText('عنوان فایل PDF'),
            Forms\Components\Textarea::make('description')->label('توضیحات')->helperText('شرح محصول'),
            Forms\Components\TextInput::make('file_path')->label('مسیر فایل')->required()->helperText('مسیر فایل در storage'),
            Forms\Components\TextInput::make('thumbnail')->label('تصویر شاخص')->helperText('مسیر تصویر بندانگشتی'),
            Forms\Components\TextInput::make('price')->label('قیمت')->numeric()->suffix('ریال')->helperText('قیمت به ریال'),
            Forms\Components\TextInput::make('category')->label('دسته')->helperText('دسته‌بندی محصول'),
            Forms\Components\Toggle::make('is_active')->label('فعال')->helperText('قابل خرید بودن محصول'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable(),
                Tables\Columns\TextColumn::make('price')->label('قیمت')->suffix(' ریال'),
                Tables\Columns\TextColumn::make('download_count')->label('دانلود'),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('ویرایش'),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('حذف'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPdfProducts::route('/'),
            'create' => Pages\CreatePdfProduct::route('/create'),
            'edit' => Pages\EditPdfProduct::route('/{record}/edit'),
        ];
    }
}