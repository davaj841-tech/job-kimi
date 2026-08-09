<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'کاربران';

    protected static ?string $modelLabel = 'کاربر';

    protected static ?string $pluralModelLabel = 'کاربران';

    protected static ?string $navigationGroup = 'مدیریت کاربران';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('mobile')
                ->label('موبایل')
                ->required()
                ->unique(ignoreRecord: true)
                ->helperText('شماره موبایل ۱۱ رقمی برای ورود با OTP'),
            Forms\Components\TextInput::make('name')
                ->label('نام')
                ->helperText('نام نمایشی کاربر'),
            Forms\Components\TextInput::make('email')
                ->label('ایمیل')
                ->email()
                ->helperText('ایمیل اختیاری کاربر'),
            Forms\Components\TextInput::make('national_code')
                ->label('کد ملی')
                ->helperText('کد ملی ۱۰ رقمی ایران'),
            Forms\Components\Select::make('role')
                ->label('نقش')
                ->options([
                    'jobseeker' => 'کارجو',
                    'employer' => 'کارفرما',
                    'operator' => 'اپراتور',
                    'admin' => 'ادمین',
                ])
                ->required()
                ->helperText('سطح دسترسی کاربر در سامانه'),
            Forms\Components\TextInput::make('wallet_balance')
                ->label('موجودی کیف پول')
                ->numeric()
                ->suffix('ریال')
                ->helperText('موجودی کیف پول به ریال'),
            Forms\Components\Select::make('subscription_plan_id')
                ->label('پلن اشتراک')
                ->relationship('subscriptionPlan', 'name')
                ->helperText('پلن اشتراک فعال کاربر'),
            Forms\Components\DateTimePicker::make('subscription_expires_at')
                ->label('انقضای اشتراک')
                ->helperText('تاریخ پایان اشتراک'),
            Forms\Components\TextInput::make('password')
                ->label('رمز عبور')
                ->password()
                ->dehydrated(fn ($state) => filled($state))
                ->helperText('فقط برای ورود به پنل ادمین'),
            Forms\Components\Toggle::make('is_verified')
                ->label('تایید شده')
                ->helperText('آیا موبایل کاربر تایید شده است'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mobile')->label('موبایل')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('نام')->searchable(),
                Tables\Columns\TextColumn::make('role')->label('نقش')->badge(),
                Tables\Columns\TextColumn::make('wallet_balance')->label('کیف پول')->suffix(' ریال'),
                Tables\Columns\IconColumn::make('is_verified')->label('تایید')->boolean(),
                Tables\Columns\TextColumn::make('subscriptionPlan.name')->label('اشتراک'),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
