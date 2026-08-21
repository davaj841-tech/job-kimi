<?php

namespace App\Filament\Widgets;

use App\Services\Seo\CannibalizationService;
use App\Services\Seo\SeoScoreService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SeoDashboardWidget extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        $score = app(SeoScoreService::class);
        $dist = $score->getScoreDistribution();
        $cannibalization = app(CannibalizationService::class)->findCannibalization();

        return [
            Stat::make('میانگین SEO', number_format($score->getAverageScore(), 0)),
            Stat::make('عالی', $dist['excellent'])
                ->color('success'),
            Stat::make('نیاز به بهبود', $dist['needs_improvement'] + $dist['poor'])
                ->color('warning'),
            Stat::make('Cannibalization', count($cannibalization))
                ->color(count($cannibalization) > 0 ? 'danger' : 'success'),
        ];
    }
}
