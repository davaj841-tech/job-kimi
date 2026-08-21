<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithStaffAccess;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\StaffRoles;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Morilog\Jalali\Jalalian;

class UserResource extends Resource
{
    use InteractsWithStaffAccess;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'کاربران';

    protected static ?string $modelLabel = 'کاربر';

    protected static ?string $pluralModelLabel = 'کاربران';

    protected static ?string $navigationGroup = 'مدیریت کاربران';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return self::superAdminOnly();
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return self::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return self::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return self::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات کاربر')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('mobile')
                        ->label('موبایل')
                        ->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('name')
                        ->label('نام'),
                    Forms\Components\TextInput::make('email')
                        ->label('ایمیل')
                        ->email()
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('role')
                        ->label('نقش')
                        ->options([
                            'jobseeker' => 'کارجو',
                            'employer' => 'کارفرما',
                            'operator' => 'اپراتور',
                            'admin' => 'ادمین',
                            'super_admin' => 'سوپرادمین',
                        ])
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->label('وضعیت')
                        ->options([
                            'active' => 'فعال',
                            'blocked' => 'مسدود',
                        ])
                        ->required()
                        ->default('active'),
                    Forms\Components\Toggle::make('is_verified')
                        ->label('تایید شده'),
                    Forms\Components\TextInput::make('wallet_balance')
                        ->label('موجودی کیف پول')
                        ->numeric()
                        ->suffix('ریال')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('موجودی فقط از بخش کیف پول تغییر می‌کند.'),
                    Forms\Components\Select::make('subscription_plan_id')
                        ->label('پلن اشتراک')
                        ->relationship('subscriptionPlan', 'name'),
                    Forms\Components\DateTimePicker::make('subscription_expires_at')
                        ->label('انقضای اشتراک'),
                    Forms\Components\TextInput::make('password')
                        ->label('رمز عبور')
                        ->password()
                        ->dehydrated(fn ($state) => filled($state)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('نام')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('mobile')
                    ->label('موبایل')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-phone'),
                Tables\Columns\TextColumn::make('role')
                    ->label('نقش')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'operator' => 'info',
                        'employer' => 'gray',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('wallet_balance')
                    ->label('موجودی')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' ریال')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_verified')
                    ->label('تایید')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('ثبت‌نام')
                    ->formatStateUsing(fn ($state) => $state
                        ? Jalalian::fromDateTime($state)->format('Y/m/d H:i')
                        : '—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('نقش')
                    ->options([
                        'jobseeker' => 'کارجو',
                        'employer' => 'کارفرما',
                        'operator' => 'اپراتور',
                        'admin' => 'ادمین',
                        'super_admin' => 'سوپرادمین',
                    ]),
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options([
                        'active' => 'فعال',
                        'blocked' => 'مسدود',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->label('تاریخ ثبت‌نام')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('از'),
                        Forms\Components\DatePicker::make('until')->label('تا'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                            ->when($data['until'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\EditAction::make()->label('ویرایش'),
                    Tables\Actions\DeleteAction::make()
                        ->label('حذف')
                        ->requiresConfirmation()
                        ->visible(fn (User $record): bool => ! StaffRoles::isProtectedStaffAccount($record)
                            || StaffRoles::isSuperAdmin(auth()->user())),
                ])->label('عملیات'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    self::secureDeleteBulkAction('حذف انتخاب‌شده‌ها')
                        ->action(function ($records): void {
                            $records
                                ->reject(fn (User $user) => StaffRoles::isProtectedStaffAccount($user))
                                ->each->delete();
                        }),
                    self::secureBulkAction(
                        Tables\Actions\BulkAction::make('activate')
                            ->label('فعال‌سازی')
                            ->icon('heroicon-o-check')
                            ->action(fn ($records) => $records->each->update(['status' => 'active']))
                    ),
                    self::secureBulkAction(
                        Tables\Actions\BulkAction::make('deactivate')
                            ->label('مسدودسازی')
                            ->icon('heroicon-o-x-mark')
                            ->color('warning')
                            ->action(fn ($records) => $records
                                ->reject(fn (User $user) => StaffRoles::isProtectedStaffAccount($user))
                                ->each->update(['status' => 'blocked']))
                    ),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
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
