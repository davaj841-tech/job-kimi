<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ExamParticipationChart;
use App\Filament\Widgets\LatestNotifications;
use App\Filament\Widgets\RecentExamsTable;
use App\Filament\Widgets\RecentTransactionsTable;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\StatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = 'داشبورد';

    protected static ?string $navigationLabel = 'داشبورد';

    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            RevenueChart::class,
            ExamParticipationChart::class,
            RecentExamsTable::class,
            RecentTransactionsTable::class,
            LatestNotifications::class,
        ];
    }
}
