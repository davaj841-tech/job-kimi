<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionResource\Pages;
use App\Models\Question;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'سوالات';

    protected static ?string $modelLabel = 'سوال';

    protected static ?string $pluralModelLabel = 'سوالات';

    protected static ?string $navigationGroup = 'آزمون‌ها';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('exam_id')->label('آزمون')->relationship('exam', 'title')->required()->helperText('آزمون مربوطه'),
            Forms\Components\RichEditor::make('question_text')->label('متن سوال')->required()->columnSpanFull()->helperText('متن سوال — از ویرایشگر برای فرمول استفاده کنید'),
            Forms\Components\Select::make('question_type')->label('نوع')->options([
                'multiple_choice' => 'چهارگزینه‌ای',
                'formula' => 'فرمولی',
            ])->required()->helperText('نوع سوال'),
            Forms\Components\RichEditor::make('option_a')->label('گزینه الف')->helperText('متن گزینه الف'),
            Forms\Components\RichEditor::make('option_b')->label('گزینه ب')->helperText('متن گزینه ب'),
            Forms\Components\RichEditor::make('option_c')->label('گزینه ج')->helperText('متن گزینه ج'),
            Forms\Components\RichEditor::make('option_d')->label('گزینه د')->helperText('متن گزینه د'),
            Forms\Components\Select::make('correct_answer')->label('پاسخ صحیح')->options([
                'a' => 'الف', 'b' => 'ب', 'c' => 'ج', 'd' => 'د',
            ])->required()->helperText('گزینه صحیح'),
            Forms\Components\RichEditor::make('explanation')->label('توضیح')->columnSpanFull()->helperText('توضیح پاسخ صحیح'),
            Forms\Components\Select::make('difficulty')->label('سطح')->options([
                'easy' => 'آسان', 'medium' => 'متوسط', 'hard' => 'سخت',
            ])->default('medium')->helperText('اختیاری — پیش‌فرض متوسط'),
            Forms\Components\Select::make('subject')->label('درس')->options(
                fn () => \App\Models\ExamSubject::query()
                    ->orderBy('sort_order')
                    ->pluck('name', 'slug')
                    ->all()
            )->searchable()->helperText('موضوع سوال'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('exam.title')->label('آزمون')->limit(20),
                Tables\Columns\TextColumn::make('question_text')->label('سوال')->html()->limit(40),
                Tables\Columns\TextColumn::make('correct_answer')->label('پاسخ')->formatStateUsing(
                    fn ($state) => \App\Services\ReportCardPDFService::optionLetter($state)
                ),
                Tables\Columns\TextColumn::make('difficulty')->label('سختی')->badge(),
                Tables\Columns\TextColumn::make('subject')->label('ماده'),
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
            'index' => Pages\ListQuestions::route('/'),
            'create' => Pages\CreateQuestion::route('/create'),
            'edit' => Pages\EditQuestion::route('/{record}/edit'),
        ];
    }
}
