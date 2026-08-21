<?php

namespace App\Filament\Resources\Seo;

use App\Filament\Concerns\InteractsWithStaffAccess;
use App\Models\Seo\SeoAnalysis;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SeoAnalysisResource extends Resource
{
    use InteractsWithStaffAccess;

    protected static ?string $model = SeoAnalysis::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'SEO';

    protected static ?string $navigationLabel = 'تحلیل SEO';

    protected static ?string $modelLabel = 'تحلیل';

    protected static ?string $pluralModelLabel = 'تحلیل‌ها';

    protected static ?int $navigationSort = 2;

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
                Tables\Columns\TextColumn::make('analyzable_type')->label('نوع')->formatStateUsing(fn ($state) => class_basename($state)),
                Tables\Columns\TextColumn::make('analyzable_id')->label('شناسه'),
                Tables\Columns\TextColumn::make('score')->label('امتیاز')->sortable()->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 90 => 'success',
                        $state >= 75 => 'info',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()
                    ->color(fn ($state) => match ($state) {
                        'excellent' => 'success',
                        'good' => 'info',
                        'needs_improvement' => 'warning',
                        'poor' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('analyzed_at')->label('آخرین تحلیل')->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->defaultSort('score', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'excellent' => 'عالی',
                    'good' => 'خوب',
                    'needs_improvement' => 'نیاز به بهبود',
                    'poor' => 'ضعیف',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Seo\SeoAnalysisResource\Pages\ListSeoAnalyses::route('/'),
        ];
    }
}
