<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;

class RevenueChart extends ChartWidget
{
    protected static ?string $heading = 'درآمد ۳۰ روز اخیر';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 2;

    protected function getData(): array
    {
        $labels = [];
        $values = [];

        foreach (range(29, 0) as $days) {
            $day = Carbon::today()->subDays($days);
            $labels[] = Jalalian::fromDateTime($day)->format('m/d');
            $values[] = (int) Transaction::query()
                ->whereDate('created_at', $day)
                ->where('status', Transaction::STATUS_COMPLETED)
                ->sum('amount');
        }

        return [
            'datasets' => [
                [
                    'label' => 'درآمد (ریال)',
                    'data' => $values,
                    'borderColor' => '#ef394e',
                    'backgroundColor' => 'rgba(239, 57, 78, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
