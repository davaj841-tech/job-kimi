<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Morilog\Jalali\Jalalian;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'تراکنش‌ها';

    protected static ?string $modelLabel = 'تراکنش';

    protected static ?string $pluralModelLabel = 'تراکنش‌ها';

    protected static ?string $navigationGroup = 'اشتراک و مالی';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')->label('کاربر')->relationship('user', 'mobile')->disabled(),
            Forms\Components\TextInput::make('amount')->label('مبلغ')->disabled()->suffix('ریال'),
            Forms\Components\TextInput::make('type')->label('نوع')->disabled(),
            Forms\Components\TextInput::make('gateway')->label('درگاه')->disabled(),
            Forms\Components\TextInput::make('status')->label('وضعیت')->disabled(),
            Forms\Components\TextInput::make('reference_id')->label('کد پیگیری')->disabled(),
            Forms\Components\Textarea::make('description')->label('توضیحات')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('شناسه')
                    ->copyable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('کاربر')
                    ->searchable()
                    ->description(fn (Transaction $record) => $record->user?->mobile),
                Tables\Columns\TextColumn::make('amount')
                    ->label('مبلغ')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' ریال')
                    ->sortable()
                    ->color(fn (Transaction $record) => $record->type === 'deposit' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'deposit' => 'success',
                        'withdrawal' => 'danger',
                        'purchase' => 'warning',
                        'refund' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('gateway')
                    ->label('درگاه')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('reference_id')
                    ->label('کد پیگیری')
                    ->copyable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ')
                    ->formatStateUsing(fn ($state) => $state
                        ? Jalalian::fromDateTime($state)->format('Y/m/d H:i')
                        : '—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options([
                        'success' => 'موفق',
                        'pending' => 'در انتظار',
                        'failed' => 'ناموفق',
                    ]),
                SelectFilter::make('type')
                    ->label('نوع')
                    ->options([
                        'deposit' => 'واریز',
                        'withdrawal' => 'برداشت',
                        'purchase' => 'پرداخت',
                        'refund' => 'برگشت',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->label('تاریخ')
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
                Tables\Actions\ViewAction::make()->label('مشاهده'),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'view' => Pages\ViewTransaction::route('/{record}'),
        ];
    }
}
