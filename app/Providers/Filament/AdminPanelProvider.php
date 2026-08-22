<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\QuickActions;
use App\Filament\Widgets\ExamParticipationChart;
use App\Filament\Widgets\LatestNotifications;
use App\Filament\Widgets\RecentExamsTable;
use App\Filament\Widgets\RecentTransactionsTable;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\SeoDashboardWidget;
use App\Filament\Widgets\StatsOverview;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path((string) config('app.filament_path', 'filament'))
            ->login()
            ->brandName('جاب‌آزمون')
            ->font('Vazirmatn')
            ->colors([
                'primary' => Color::Rose,
                'danger' => Color::Red,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'info' => Color::Blue,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16rem')
            ->maxContentWidth('full')
            ->spa()
            ->breadcrumbs(false)
            ->navigationGroups([
                'مدیریت محتوا',
                'آزمون‌ها',
                'مدیریت کاربران',
                'اشتراک و مالی',
                'مالی',
                'تنظیمات',
                'سیستم',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
                QuickActions::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                StatsOverview::class,
                RevenueChart::class,
                ExamParticipationChart::class,
                RecentExamsTable::class,
                RecentTransactionsTable::class,
                LatestNotifications::class,
                SeoDashboardWidget::class,
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                'panels::head.end',
                function (): string {
                    $theme = '';
                    $path = resource_path('css/filament/admin/theme.css');
                    if (is_file($path)) {
                        $theme = '<style>'.file_get_contents($path).'</style>';
                    }

                    return '<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />'.$theme;
                }
            );
    }
}
