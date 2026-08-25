<?php

namespace App\Filament\Resources\Seo;

use App\Filament\Concerns\InteractsWithStaffAccess;
use App\Filament\Resources\Seo\SeoKeywordResource\Pages\ListSeoKeywords;
use App\Models\Seo\SeoKeyword;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SeoKeywordResource extends Resource
{
    use InteractsWithStaffAccess;

    protected static ?string $model = SeoKeyword::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'SEO';

    protected static ?string $navigationLabel = 'کلمات کلیدی';

    protected static ?string $modelLabel = 'کلمه کلیدی';

    protected static ?string $pluralModelLabel = 'کلمات کلیدی';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return self::superAdminOnly();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('focus_keyword')->label('کلمه کلیدی')->searchable(),
                Tables\Columns\TextColumn::make('keywordable_type')->label('نوع')->formatStateUsing(fn ($state) => class_basename($state)),
                Tables\Columns\TextColumn::make('keywordable_id')->label('شناسه'),
                Tables\Columns\TextColumn::make('search_intent')->label('هدف جستجو')->badge(),
            ])
            ->defaultSort('focus_keyword');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeoKeywords::route('/'),
        ];
    }
}
