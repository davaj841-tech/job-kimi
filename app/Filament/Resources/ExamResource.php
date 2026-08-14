<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExamResource\Pages;
use App\Models\Exam;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Morilog\Jalali\Jalalian;

class ExamResource extends Resource
{
    protected static ?string $model = Exam::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'آزمون‌ها';

    protected static ?string $modelLabel = 'آزمون';

    protected static ?string $pluralModelLabel = 'آزمون‌ها';

    protected static ?string $navigationGroup = 'مدیریت محتوا';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Exam')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('اطلاعات اصلی')
                        ->schema([
                            Forms\Components\TextInput::make('title')->label('عنوان')->required(),
                            Forms\Components\TextInput::make('slug')->label('اسلاگ'),
                            Forms\Components\Select::make('category_id')->label('طبقه‌بندی')->relationship('category', 'name')->required()->searchable()->preload(),
                            Forms\Components\Select::make('job_post_id')->label('آگهی مرتبط')->relationship('jobPost', 'title')->searchable()->preload(),
                            Forms\Components\Select::make('status')->label('وضعیت')->options([
                                'draft' => 'پیش‌نویس',
                                'published' => 'منتشر شده',
                                'archived' => 'بایگانی',
                            ])->required()->default('draft'),
                            Forms\Components\Select::make('created_by')->label('ایجادکننده')->relationship('creator', 'name')->required(),
                            Forms\Components\Textarea::make('description')->label('توضیحات')->rows(4)->columnSpanFull(),
                        ])->columns(2),
                    Forms\Components\Tabs\Tab::make('تنظیمات آزمون')
                        ->schema([
                            Forms\Components\TextInput::make('duration_minutes')->label('مدت (دقیقه)')->numeric()->default(60),
                            Forms\Components\TextInput::make('passing_score')->label('نمره قبولی')->numeric()->default(70),
                            Forms\Components\TextInput::make('total_questions')->label('تعداد سوال')->numeric(),
                            Forms\Components\TextInput::make('total_marks')->label('مجموع نمرات')->numeric(),
                            Forms\Components\Toggle::make('has_negative_marking')->label('نمره منفی')->live(),
                            Forms\Components\TextInput::make('negative_mark_ratio')
                                ->label('نسبت نمره منفی')
                                ->numeric()
                                ->step(0.0001)
                                ->default(0.3333)
                                ->visible(fn (Forms\Get $get): bool => (bool) $get('has_negative_marking')),
                        ])->columns(2),
                    Forms\Components\Tabs\Tab::make('قیمت‌گذاری')
                        ->schema([
                            Forms\Components\Toggle::make('is_free')->label('رایگان')->live()
                                ->afterStateUpdated(function (Forms\Set $set, $state): void {
                                    if ($state) {
                                        $set('price', 0);
                                    }
                                }),
                            Forms\Components\TextInput::make('price')->label('قیمت')->numeric()->suffix('ریال'),
                            Forms\Components\Select::make('subscription_required')->label('نیاز به اشتراک')->options([
                                'free' => 'فقط رایگان',
                                'paid' => 'فقط پولی',
                                'any' => 'همه',
                            ]),
                        ])->columns(2),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('طبقه‌بندی')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('questions_count')
                    ->label('سوالات')
                    ->counts('questions')
                    ->sortable(),
                Tables\Columns\TextColumn::make('attempts_count')
                    ->label('تلاش‌ها')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        'archived' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_free')->label('رایگان')->boolean(),
                Tables\Columns\TextColumn::make('price')->label('قیمت')->suffix(' ریال')->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('ایجاد')
                    ->formatStateUsing(fn ($state) => $state
                        ? Jalalian::fromDateTime($state)->format('Y/m/d')
                        : '—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options([
                        'draft' => 'پیش‌نویس',
                        'published' => 'منتشر شده',
                        'archived' => 'بایگانی',
                    ]),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('طبقه‌بندی')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_free')->label('رایگان')->boolean(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()->label('ویرایش'),
                    Tables\Actions\Action::make('duplicate')
                        ->label('کپی')
                        ->icon('heroicon-o-document-duplicate')
                        ->requiresConfirmation()
                        ->action(function (Exam $record): void {
                            $new = $record->replicate(['slug', 'attempts_count', 'avg_rating']);
                            $new->title = $record->title.' (کپی)';
                            $new->slug = null;
                            $new->status = 'draft';
                            $new->save();
                        }),
                    Tables\Actions\DeleteAction::make()->label('حذف'),
                ])->label('عملیات'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('حذف'),
                    Tables\Actions\BulkAction::make('publish')
                        ->label('انتشار')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['status' => 'published'])),
                    Tables\Actions\BulkAction::make('archive')
                        ->label('بایگانی')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->action(fn ($records) => $records->each->update(['status' => 'archived'])),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExams::route('/'),
            'create' => Pages\CreateExam::route('/create'),
            'edit' => Pages\EditExam::route('/{record}/edit'),
        ];
    }
}
