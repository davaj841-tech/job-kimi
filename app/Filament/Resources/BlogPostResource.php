<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlogPostResource\Pages;
use App\Models\BlogPost;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'بلاگ';

    protected static ?string $modelLabel = 'پست بلاگ';

    protected static ?string $pluralModelLabel = 'پست‌های بلاگ';

    protected static ?string $navigationGroup = 'محتوا';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('عنوان')->required()->helperText('عنوان مطلب'),
            Forms\Components\TextInput::make('slug')->label('اسلاگ')->helperText('شناسه URL'),
            Forms\Components\RichEditor::make('content')->label('محتوا')->columnSpanFull()->helperText('متن کامل مطلب'),
            Forms\Components\Textarea::make('excerpt')->label('خلاصه')->helperText('خلاصه کوتاه برای لیست'),
            Forms\Components\TextInput::make('featured_image')->label('تصویر شاخص')->helperText('مسیر تصویر'),
            Forms\Components\TextInput::make('category')->label('دسته‌بندی')->helperText('دسته مطلب'),
            Forms\Components\TextInput::make('meta_title')->label('عنوان سئو')->maxLength(255),
            Forms\Components\Textarea::make('meta_description')->label('توضیح سئو')->maxLength(500),
            Forms\Components\Select::make('status')->label('وضعیت')->options([
                'draft' => 'پیش‌نویس', 'published' => 'منتشر شده',
            ])->required()->helperText('وضعیت انتشار'),
            Forms\Components\Select::make('ai_content_id')->label('محتوای AI')->relationship('aiContent', 'id')->helperText('محتوای تولیدشده توسط AI'),
            Forms\Components\Select::make('created_by')->label('نویسنده')->relationship('creator', 'name')->required()->helperText('نویسنده مطلب'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable(),
                Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge(),
                Tables\Columns\TextColumn::make('creator.name')->label('نویسنده'),
                Tables\Columns\TextColumn::make('created_at')->label('تاریخ')->dateTime(),
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
            'index' => Pages\ListBlogPosts::route('/'),
            'create' => Pages\CreateBlogPost::route('/create'),
            'edit' => Pages\EditBlogPost::route('/{record}/edit'),
        ];
    }
}
