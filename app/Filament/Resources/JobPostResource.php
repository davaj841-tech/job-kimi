<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithStaffAccess;
use App\Filament\Forms\SeoFormSchema;
use App\Filament\Resources\JobPostResource\Pages;
use App\Models\JobPost;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class JobPostResource extends Resource
{
    use InteractsWithStaffAccess;

    protected static ?string $model = JobPost::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'آگهی‌های شغلی';

    protected static ?string $modelLabel = 'آگهی شغلی';

    protected static ?string $pluralModelLabel = 'آگهی‌های شغلی';

    protected static ?string $navigationGroup = 'استخدام';

    public static function canViewAny(): bool
    {
        return self::staffAdminOnly();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('عنوان')->required()->helperText('عنوان آگهی استخدام'),
            Forms\Components\TextInput::make('company_name')->label('شرکت')->required()->helperText('نام سازمان/شرکت'),
            Forms\Components\Textarea::make('description')->label('توضیحات')->columnSpanFull()->helperText('شرح کامل آگهی'),
            Forms\Components\TextInput::make('province')->label('استان')->helperText('استان محل خدمت'),
            Forms\Components\TextInput::make('city')->label('شهر')->helperText('شهر محل خدمت'),
            Forms\Components\TextInput::make('job_category')->label('طبقه‌بندی')->helperText('طبقه‌بندی شغل'),
            Forms\Components\DateTimePicker::make('registration_deadline')->label('مهلت ثبت‌نام')->helperText('آخرین مهلت ثبت‌نام'),
            Forms\Components\DateTimePicker::make('exam_date')->label('تاریخ آزمون')->helperText('زمان برگزاری آزمون'),
            Forms\Components\TextInput::make('registration_link')->label('لینک ثبت‌نام')->url()->helperText('لینک ثبت‌نام رسمی'),
            Forms\Components\TextInput::make('source_url')->label('منبع')->url()->helperText('آدرس منبع آگهی'),
            Forms\Components\Select::make('status')->label('وضعیت')->options([
                'pending' => 'در انتظار',
                'approved' => 'تایید شده',
                'rejected' => 'رد شده',
            ])->required()->helperText('وضعیت تایید آگهی'),
            Forms\Components\Toggle::make('is_featured')->label('ویژه')->helperText('نمایش در بخش ویژه'),
            Forms\Components\Select::make('created_by')->label('ایجادکننده')->relationship('creator', 'name')->required()->helperText('کاربر ثبت‌کننده'),
            SeoFormSchema::section(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('company_name')->label('شرکت'),
                Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge(),
                Tables\Columns\IconColumn::make('is_featured')->label('ویژه')->boolean(),
                Tables\Columns\TextColumn::make('view_count')->label('بازدید'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('ویرایش'),
                Tables\Actions\Action::make('approve')
                    ->label('تایید')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->status !== 'approved')
                    ->action(fn ($record) => $record->update(['status' => 'approved', 'approved_by' => auth()->id()])),
                Tables\Actions\Action::make('reject')
                    ->label('رد')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status !== 'rejected')
                    ->action(fn ($record) => $record->update(['status' => 'rejected', 'approved_by' => auth()->id()])),
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
            'index' => Pages\ListJobPosts::route('/'),
            'create' => Pages\CreateJobPost::route('/create'),
            'edit' => Pages\EditJobPost::route('/{record}/edit'),
        ];
    }
}
