<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeatureResource\Pages;
use App\Models\Feature;
use App\Services\FeatureFlagService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class FeatureResource extends Resource
{
    protected static ?string $model = Feature::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'قابلیت‌ها';

    protected static ?string $modelLabel = 'قابلیت';

    protected static ?string $pluralModelLabel = 'قابلیت‌ها';

    protected static ?string $navigationGroup = 'سیستم';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('نام')
                ->required()
                ->maxLength(64)
                ->unique(ignoreRecord: true)
                ->disabled(fn (?Feature $record): bool => $record !== null)
                ->dehydrated()
                ->helperText('کلید یکتا مثل ai-resume'),
            Forms\Components\Toggle::make('enabled')
                ->label('فعال')
                ->default(false),
            Forms\Components\TextInput::make('description')
                ->label('توضیح')
                ->maxLength(255),
            Forms\Components\KeyValue::make('config')
                ->label('پیکربندی')
                ->keyLabel('کلید')
                ->valueLabel('مقدار')
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('نام')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('enabled')
                    ->label('فعال'),
                Tables\Columns\TextColumn::make('description')
                    ->label('توضیح')
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('به‌روزرسانی')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make()->label('ویرایش'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('enableAll')
                    ->label('فعال‌سازی همه')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        app(FeatureFlagService::class)->enableAll();
                        Notification::make()->title('همه قابلیت‌ها فعال شدند.')->success()->send();
                    }),
                Tables\Actions\Action::make('disableAll')
                    ->label('غیرفعال‌سازی همه')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        app(FeatureFlagService::class)->disableAll();
                        Notification::make()->title('همه قابلیت‌ها غیرفعال شدند.')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('enableSelected')
                        ->label('فعال کردن')
                        ->icon('heroicon-o-check')
                        ->action(function (Collection $records): void {
                            $records->each(fn (Feature $feature) => $feature->update(['enabled' => true]));
                            app(FeatureFlagService::class)->forgetCache();
                        }),
                    Tables\Actions\BulkAction::make('disableSelected')
                        ->label('غیرفعال کردن')
                        ->icon('heroicon-o-x-mark')
                        ->action(function (Collection $records): void {
                            $records->each(fn (Feature $feature) => $feature->update(['enabled' => false]));
                            app(FeatureFlagService::class)->forgetCache();
                        }),
                    Tables\Actions\DeleteBulkAction::make()->label('حذف'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeatures::route('/'),
            'create' => Pages\CreateFeature::route('/create'),
            'edit' => Pages\EditFeature::route('/{record}/edit'),
        ];
    }
}
