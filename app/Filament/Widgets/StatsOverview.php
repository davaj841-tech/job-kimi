<?php

namespace App\Filament\Widgets;

use App\Models\Exam;
use App\Models\Transaction;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = now()->startOfDay();

        $todayRevenue = (int) Transaction::query()
            ->where('created_at', '>=', $today)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->sum('amount');

        $online = User::query()
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->whereIn('role', ['jobseeker', 'employer', 'operator', 'admin', 'super_admin'])
            ->count();

        return [
            Stat::make('کاربران امروز', (string) User::query()->whereDate('created_at', $today)->count())
                ->description('ثبت‌نام جدید')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success'),

            Stat::make('آزمون‌های منتشر', (string) Exam::query()->where('status', 'published')->count())
                ->description('وضعیت منتشر شده')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            Stat::make('درآمد امروز', number_format($todayRevenue).' ریال')
                ->description('تراکنش موفق')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('کاربران فعال', (string) $online)
                ->description('فعالیت ۱۰ دقیقه اخیر')
                ->descriptionIcon('heroicon-m-signal')
                ->color('info'),
        ];
    }
}
