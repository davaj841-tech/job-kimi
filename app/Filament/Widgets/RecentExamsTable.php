<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ExamResource;
use App\Models\Exam;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Morilog\Jalali\Jalalian;

class RecentExamsTable extends BaseWidget
{
    protected static ?string $heading = 'آخرین آزمون‌ها';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(Exam::query()->latest())
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان')
                    ->limit(28)
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        'archived' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ')
                    ->formatStateUsing(fn ($state) => $state
                        ? Jalalian::fromDateTime($state)->format('Y/m/d')
                        : '—'),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('ویرایش')
                    ->url(fn (Exam $record): string => ExamResource::getUrl('edit', ['record' => $record])),
            ])
            ->paginated([8]);
    }
}
