<?php

namespace App\Filament\Forms;

use Filament\Forms;

class SeoFormSchema
{
    public static function section(): Forms\Components\Section
    {
        return Forms\Components\Section::make('SEO')
            ->schema([
                Forms\Components\TextInput::make('seo_title')
                    ->label('SEO Title')
                    ->maxLength(160),
                Forms\Components\Textarea::make('seo_description')
                    ->label('Meta Description')
                    ->maxLength(320)
                    ->rows(3),
                Forms\Components\TextInput::make('seo_focus_keyword')
                    ->label('Focus Keyword')
                    ->maxLength(100),
                Forms\Components\TextInput::make('seo_canonical')
                    ->label('Canonical URL')
                    ->url(),
                Forms\Components\Select::make('seo_robots')
                    ->label('Robots')
                    ->options([
                        'index, follow' => 'index, follow',
                        'noindex, follow' => 'noindex, follow',
                        'index, nofollow' => 'index, nofollow',
                        'noindex, nofollow' => 'noindex, nofollow',
                    ])
                    ->default('index, follow'),
                Forms\Components\TextInput::make('seo_og_image')
                    ->label('OG Image')
                    ->helperText('مسیر یا URL تصویر'),
                Forms\Components\TextInput::make('seo_twitter_image')
                    ->label('Twitter Image')
                    ->helperText('در صورت خالی بودن از OG Image استفاده می‌شود'),
                Forms\Components\Placeholder::make('seo_score_display')
                    ->label('SEO Score')
                    ->content(fn ($record) => $record?->seoAnalysis?->score !== null
                        ? (string) $record->seoAnalysis->score
                        : '—'),
            ])
            ->columns(2)
            ->collapsible();
    }
}
