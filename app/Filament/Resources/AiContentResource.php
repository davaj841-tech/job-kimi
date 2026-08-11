<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiContentResource\Pages;
use App\Models\AiContent;
use App\Repositories\AiContentRepository;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AiContentResource extends Resource
{
    protected static ?string $model = AiContent::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'محتوای AI';

    protected static ?string $modelLabel = 'محتوای AI';

    protected static ?string $pluralModelLabel = 'محتواهای AI';

    protected static ?string $navigationGroup = 'محتوا';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')->label('نوع')->options([
                'exam_question' => 'سوال آزمون',
                'job_tip' => 'نکته شغلی',
                'resume_tip' => 'نکته رزومه',
                'blog_post' => 'پست بلاگ',
                'job_crawl' => 'خزش آگهی',
            ])->required()->helperText('نوع محتوای تولیدشده'),
            Forms\Components\Textarea::make('prompt')->label('پرامپت')->columnSpanFull()->helperText('دستور ارسال‌شده به AI'),
            Forms\Components\Textarea::make('generated_content')->label('محتوای تولیدشده')->columnSpanFull()->helperText('خروجی AI'),
            Forms\Components\Select::make('status')->label('وضعیت')->options([
                'pending' => 'در انتظار', 'approved' => 'تایید', 'rejected' => 'رد',
            ])->required()->helperText('وضعیت بازبینی'),
            Forms\Components\KeyValue::make('metadata')->label('متادیتا')->helperText('اطلاعات جانبی'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->label('نوع')->badge(),
                Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge(),
                Tables\Columns\TextColumn::make('generated_content')->label('محتوا')->limit(40),
                Tables\Columns\TextColumn::make('reviewer.name')->label('بازبین'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('ویرایش'),
                Tables\Actions\Action::make('approve')
                    ->label('تایید')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record) {
                        try {
                            app(AiContentRepository::class)
                                ->approve($record->id, auth()->id());

                            Notification::make()
                                ->title('تایید شد')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('خطا در تایید')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('رد')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record) {
                        app(AiContentRepository::class)
                            ->reject($record->id, auth()->id());
                    }),
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
            'index' => Pages\ListAiContents::route('/'),
            'create' => Pages\CreateAiContent::route('/create'),
            'edit' => Pages\EditAiContent::route('/{record}/edit'),
        ];
    }
}
