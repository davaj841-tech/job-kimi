<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExamResource\Pages;
use App\Models\Exam;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExamResource extends Resource
{
    protected static ?string $model = Exam::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'آزمون‌ها';

    protected static ?string $modelLabel = 'آزمون';

    protected static ?string $pluralModelLabel = 'آزمون‌ها';

    protected static ?string $navigationGroup = 'آزمون‌ها';

    

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('عنوان')->required()->helperText('عنوان آزمون'),
            Forms\Components\TextInput::make('slug')->label('اسلاگ')->helperText('شناسه URL'),
            Forms\Components\Select::make('category_id')->label('دسته')->relationship('category', 'name')->required()->helperText('دسته‌بندی آزمون'),
            Forms\Components\Select::make('job_post_id')->label('آگهی مرتبط')->relationship('jobPost', 'title')->helperText('آگهی شغلی مرتبط (اختیاری)'),
            Forms\Components\Textarea::make('description')->label('توضیحات')->columnSpanFull()->helperText('شرح آزمون'),
            Forms\Components\TextInput::make('duration_minutes')->label('مدت (دقیقه)')->numeric()->helperText('زمان آزمون به دقیقه'),
            Forms\Components\TextInput::make('passing_score')->label('نمره قبولی')->numeric()->helperText('حداقل نمره قبولی'),
            Forms\Components\TextInput::make('total_questions')->label('تعداد سوال')->numeric()->helperText('تعداد کل سوالات'),
            Forms\Components\TextInput::make('total_marks')->label('مجموع نمرات')->numeric()->helperText('حداکثر نمره'),
            Forms\Components\Toggle::make('has_negative_marking')->label('نمره منفی')->helperText('برای پاسخ غلط نمره منفی اعمال شود؟'),
            Forms\Components\TextInput::make('negative_mark_ratio')->label('نسبت نمره منفی')->numeric()->step(0.0001)->default(0.3333)->helperText('مثلاً ۰٫۳۳۳۳ یعنی یک‌سوم نمره هر سوال'),
            Forms\Components\Toggle::make('is_free')->label('رایگان')->helperText('آزمون رایگان است؟'),
            Forms\Components\TextInput::make('price')->label('قیمت')->numeric()->suffix('ریال')->helperText('قیمت آزمون به ریال'),
            Forms\Components\Select::make('subscription_required')->label('نیاز به اشتراک')->options([
                'free' => 'فقط رایگان',
                'paid' => 'فقط پولی',
                'any' => 'همه',
            ])->helperText('نوع اشتراک مورد نیاز'),
            Forms\Components\Select::make('status')->label('وضعیت')->options([
                'draft' => 'پیش‌نویس',
                'published' => 'منتشر شده',
                'archived' => 'بایگانی',
            ])->required()->helperText('وضعیت انتشار'),
            Forms\Components\Select::make('created_by')->label('ایجادکننده')->relationship('creator', 'name')->required()->helperText('کاربر سازنده'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable(),
                Tables\Columns\TextColumn::make('category.name')->label('دسته'),
                Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge(),
                Tables\Columns\IconColumn::make('is_free')->label('رایگان')->boolean(),
                Tables\Columns\TextColumn::make('price')->label('قیمت')->suffix(' ریال'),
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
            'index' => Pages\ListExams::route('/'),
            'create' => Pages\CreateExam::route('/create'),
            'edit' => Pages\EditExam::route('/{record}/edit'),
        ];
    }
}