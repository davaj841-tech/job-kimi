<?php

namespace App\Filament\Resources\Seo;

use App\Filament\Concerns\InteractsWithStaffAccess;
use App\Filament\Resources\Seo\SeoRedirectResource\Pages\CreateSeoRedirect;
use App\Filament\Resources\Seo\SeoRedirectResource\Pages\EditSeoRedirect;
use App\Filament\Resources\Seo\SeoRedirectResource\Pages\ListSeoRedirects;
use App\Models\Seo\SeoRedirect;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SeoRedirectResource extends Resource
{
    use InteractsWithStaffAccess;

    protected static ?string $model = SeoRedirect::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'SEO';

    protected static ?string $navigationLabel = 'ریدایرکت‌ها';

    protected static ?string $modelLabel = 'ریدایرکت';

    protected static ?string $pluralModelLabel = 'ریدایرکت‌ها';

    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return self::superAdminOnly();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('source_path')
                ->label('مسیر مبدأ')
                ->required()
                ->maxLength(500)
                ->prefix('/'),
            Forms\Components\TextInput::make('target_url')
                ->label('آدرس مقصد')
                ->required()
                ->maxLength(500),
            Forms\Components\Select::make('status_code')
                ->label('کد وضعیت')
                ->options([301 => '301 (دائمی)', 302 => '302 (موقت)', 410 => '410 (حذف‌شده)'])
                ->default(301)
                ->required(),
            Forms\Components\Toggle::make('is_active')
                ->label('فعال')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('source_path')->label('مبدأ')->searchable(),
                Tables\Columns\TextColumn::make('target_url')->label('مقصد')->limit(40),
                Tables\Columns\TextColumn::make('status_code')->label('کد')->badge(),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
                Tables\Columns\TextColumn::make('hits')->label('بازدید')->sortable(),
                Tables\Columns\TextColumn::make('last_hit_at')->label('آخرین')->dateTime('Y/m/d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status_code')->options([301 => '301', 302 => '302', 410 => '410']),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([self::secureDeleteBulkAction()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeoRedirects::route('/'),
            'create' => CreateSeoRedirect::route('/create'),
            'edit' => EditSeoRedirect::route('/{record}/edit'),
        ];
    }
}
