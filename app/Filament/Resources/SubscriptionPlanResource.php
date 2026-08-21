<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithStaffAccess;
use App\Filament\Resources\SubscriptionPlanResource\Pages;
use App\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionPlanResource extends Resource
{
    use InteractsWithStaffAccess;

    protected static ?string $model = SubscriptionPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'پلن‌های اشتراک';

    protected static ?string $modelLabel = 'پلن اشتراک';

    protected static ?string $pluralModelLabel = 'پلن‌های اشتراک';

    protected static ?string $navigationGroup = 'اشتراک و مالی';

    public static function canViewAny(): bool
    {
        return self::staffAdminOnly();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('نام')->required()->helperText('نام پلن اشتراک'),
            Forms\Components\TextInput::make('duration_days')->label('مدت (روز)')->numeric()->required()->helperText('مدت اعتبار به روز'),
            Forms\Components\TextInput::make('price')->label('قیمت')->numeric()->suffix('ریال')->required()->helperText('قیمت به ریال'),
            Forms\Components\KeyValue::make('features')->label('ویژگی‌ها')->helperText('لیست ویژگی‌های پلن'),
            Forms\Components\Toggle::make('is_active')->label('فعال')->helperText('آیا پلن قابل خرید است'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('نام')->searchable(),
                Tables\Columns\TextColumn::make('duration_days')->label('روز'),
                Tables\Columns\TextColumn::make('price')->label('قیمت')->suffix(' ریال'),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
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
            'index' => Pages\ListSubscriptionPlans::route('/'),
            'create' => Pages\CreateSubscriptionPlan::route('/create'),
            'edit' => Pages\EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
