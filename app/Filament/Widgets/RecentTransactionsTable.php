<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Morilog\Jalali\Jalalian;

class RecentTransactionsTable extends BaseWidget
{
    protected static ?string $heading = 'آخرین تراکنش‌ها';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(Transaction::query()->with('user')->latest())
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('کاربر')
                    ->description(fn (Transaction $record) => $record->user?->mobile)
                    ->limit(20),
                Tables\Columns\TextColumn::make('amount')
                    ->label('مبلغ')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' ریال'),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ')
                    ->formatStateUsing(fn ($state) => $state
                        ? Jalalian::fromDateTime($state)->format('Y/m/d H:i')
                        : '—'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('مشاهده')
                    ->url(fn (Transaction $record): string => TransactionResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated([8]);
    }
}
