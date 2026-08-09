<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'تراکنش‌ها';

    protected static ?string $modelLabel = 'تراکنش';

    protected static ?string $pluralModelLabel = 'تراکنش‌ها';

    protected static ?string $navigationGroup = 'اشتراک و مالی';

    public static function canCreate(): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')->label('کاربر')->relationship('user', 'mobile')->disabled()->helperText('کاربر تراکنش'),
            Forms\Components\TextInput::make('amount')->label('مبلغ')->disabled()->suffix('ریال')->helperText('مبلغ به ریال'),
            Forms\Components\TextInput::make('type')->label('نوع')->disabled()->helperText('نوع تراکنش'),
            Forms\Components\TextInput::make('gateway')->label('درگاه')->disabled()->helperText('درگاه پرداخت'),
            Forms\Components\TextInput::make('status')->label('وضعیت')->disabled()->helperText('وضعیت تراکنش'),
            Forms\Components\TextInput::make('reference_id')->label('کد پیگیری')->disabled()->helperText('شناسه مرجع درگاه'),
            Forms\Components\Textarea::make('description')->label('توضیحات')->disabled()->helperText('شرح تراکنش'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.mobile')->label('کاربر'),
                Tables\Columns\TextColumn::make('amount')->label('مبلغ')->suffix(' ریال'),
                Tables\Columns\TextColumn::make('type')->label('نوع')->badge(),
                Tables\Columns\TextColumn::make('gateway')->label('درگاه'),
                Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge(),
                Tables\Columns\TextColumn::make('reference_id')->label('پیگیری'),
                Tables\Columns\TextColumn::make('created_at')->label('تاریخ')->dateTime(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('مشاهده'),
            ])
;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'view' => Pages\ViewTransaction::route('/{record}'),
        ];
    }
}