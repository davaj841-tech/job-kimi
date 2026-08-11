<?php

namespace App\Filament\Widgets;

use App\Models\ExamAttempt;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;

class ExamParticipationChart extends ChartWidget
{
    protected static ?string $heading = 'شرکت در آزمون · ۳۰ روز';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $labels = [];
        $values = [];

        foreach (range(29, 0) as $days) {
            $day = Carbon::today()->subDays($days);
            $labels[] = Jalalian::fromDateTime($day)->format('m/d');
            $values[] = ExamAttempt::query()
                ->whereDate('created_at', $day)
                ->where('status', 'completed')
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'تلاش تکمیل‌شده',
                    'data' => $values,
                    'borderColor' => '#0f2744',
                    'backgroundColor' => 'rgba(15, 39, 68, 0.15)',
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
