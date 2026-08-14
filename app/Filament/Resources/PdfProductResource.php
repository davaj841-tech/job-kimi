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

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'محصولات PDF';

    protected static ?string $modelLabel = 'محصول PDF';

    protected static ?string $pluralModelLabel = 'محصولات PDF';

    protected static ?string $navigationGroup = 'مدیریت محتوا';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات اصلی')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')->label('عنوان')->required(),
                    Forms\Components\Textarea::make('description')->label('توضیحات')->rows(4)->columnSpanFull(),
                    Forms\Components\Select::make('job_classification_id')
                        ->label('طبقه‌بندی شغلی')
                        ->relationship('classification', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('job_post_id')
                        ->label('آگهی مرتبط')
                        ->relationship('jobPost', 'title')
                        ->searchable()
                        ->preload(),
                ]),
            Forms\Components\Section::make('فایل و قیمت')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('file_path')->label('مسیر فایل PDF')->required()
                        ->helperText('مسیر نسبی در storage'),
                    Forms\Components\TextInput::make('thumbnail')->label('کاور / تصویر')
                        ->helperText('مسیر نسبی تصویر کاور'),
                    Forms\Components\TextInput::make('price')->label('قیمت (ریال)')->numeric()->default(0)
                        ->helperText('۰ = رایگان — هر PDF جداگانه خریداری می‌شود'),
                    Forms\Components\Toggle::make('is_active')->label('منتشر / فعال')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_url')
                    ->label('')
                    ->height(48)
                    ->width(36),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable()->weight('bold')->limit(40),
                Tables\Columns\TextColumn::make('category')->label('طبقه‌بندی')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('قیمت')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' ریال')
                    ->color(fn (PdfProduct $record) => (int) $record->price <= 0 ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('purchases_count')
                    ->label('فروش')
                    ->counts('purchases')
                    ->sortable(),
                Tables\Columns\TextColumn::make('download_count')->label('دانلود')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('فعال'),
                Tables\Filters\Filter::make('free')
                    ->label('رایگان')
                    ->query(fn ($query) => $query->where('price', '<=', 0)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('ویرایش'),
                Tables\Actions\DeleteAction::make()->label('حذف'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('حذف'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
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
