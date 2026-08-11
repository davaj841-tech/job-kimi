<?php

namespace App\Filament\Widgets;

use App\Models\Exam;
use App\Models\Transaction;
use App\Models\User;
use Filament\Widgets\Widget;
use Morilog\Jalali\Jalalian;

class LatestNotifications extends Widget
{
    protected static string $view = 'filament.widgets.latest-notifications';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    /**
     * @return array{notifications: list<array{icon: string, text: string, time: string}>}
     */
    protected function getViewData(): array
    {
        $notifications = [];

        $newUsers = User::query()->whereDate('created_at', today())->count();
        if ($newUsers > 0) {
            $notifications[] = [
                'icon' => 'user-plus',
                'text' => number_format($newUsers).' کاربر جدید امروز ثبت‌نام کردند',
                'time' => 'امروز',
            ];
        }

        $latestTx = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->latest()
            ->first();
        if ($latestTx) {
            $notifications[] = [
                'icon' => 'banknotes',
                'text' => 'تراکنش '.number_format((int) $latestTx->amount).' ریالی موفق',
                'time' => Jalalian::fromDateTime($latestTx->created_at)->ago(),
            ];
        }

        $latestExam = Exam::query()->latest()->first();
        if ($latestExam) {
            $notifications[] = [
                'icon' => 'academic-cap',
                'text' => 'آزمون «'.$latestExam->title.'» ثبت شد',
                'time' => Jalalian::fromDateTime($latestExam->created_at)->ago(),
            ];
        }

        if ($notifications === []) {
            $notifications[] = [
                'icon' => 'bell',
                'text' => 'اعلان جدیدی وجود ندارد',
                'time' => Jalalian::now()->format('H:i'),
            ];
        }

        return ['notifications' => $notifications];
    }
}
