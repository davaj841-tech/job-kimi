<?php

namespace App\Services;

use App\Models\PageView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function record(array $data): ?PageView
    {
        $sessionId = substr((string) ($data['session_id'] ?? ''), 0, 64);
        $pageUrl = substr((string) ($data['page_url'] ?? '/'), 0, 500);

        if ($sessionId === '' || $pageUrl === '') {
            return null;
        }

        $exists = PageView::query()
            ->where('session_id', $sessionId)
            ->where('page_url', $pageUrl)
            ->where('created_at', '>=', now()->subMinute())
            ->exists();

        if ($exists) {
            return null;
        }

        return PageView::query()->create([
            'user_id' => $data['user_id'] ?? null,
            'session_id' => $sessionId,
            'page_url' => $pageUrl,
            'route_name' => $data['route_name'] ?? null,
            'user_agent' => isset($data['user_agent']) ? substr((string) $data['user_agent'], 0, 1000) : null,
            'ip_address' => $data['ip_address'] ?? null,
            'referrer' => isset($data['referrer']) ? substr((string) $data['referrer'], 0, 500) : null,
            'created_at' => now(),
        ]);
    }

    public function visits(?string $from, ?string $to, string $groupBy = 'day'): array
    {
        $fromAt = $from ? Carbon::parse($from)->startOfDay() : now()->subDays(29)->startOfDay();
        $toAt = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        $driver = DB::connection()->getDriverName();
        $bucketExpr = $this->bucketExpression($driver, $groupBy);

        $rows = PageView::query()
            ->whereBetween('created_at', [$fromAt, $toAt])
            ->selectRaw("{$bucketExpr} as bucket")
            ->selectRaw('COUNT(*) as page_views')
            ->selectRaw('COUNT(DISTINCT session_id) as unique_visitors')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        return $rows->map(fn ($r) => [
            'date' => $r->bucket,
            'visits' => (int) $r->page_views,
            'unique_visitors' => (int) $r->unique_visitors,
            'page_views' => (int) $r->page_views,
        ])->all();
    }

    protected function bucketExpression(string $driver, string $groupBy): string
    {
        $column = 'created_at';

        if ($driver === 'sqlite') {
            return $groupBy === 'hour'
                ? "strftime('%Y-%m-%d %H:00:00', {$column})"
                : "strftime('%Y-%m-%d', {$column})";
        }

        if (in_array($driver, ['pgsql', 'postgres'], true)) {
            return $groupBy === 'hour'
                ? "to_char({$column}, 'YYYY-MM-DD HH24:00:00')"
                : "to_char({$column}, 'YYYY-MM-DD')";
        }

        // MySQL / MariaDB
        $format = $groupBy === 'hour' ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d';

        return "DATE_FORMAT({$column}, '{$format}')";
    }

    public function topPages(int $limit = 10, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $q = PageView::query()
            ->select('page_url')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('page_url')
            ->orderByDesc('count')
            ->limit($limit);

        if ($from && $to) {
            $q->whereBetween('created_at', [$from, $to]);
        }

        return $q->get()->map(fn ($r) => [
            'page' => $r->page_url,
            'count' => (int) $r->count,
        ])->all();
    }

    public function devices(?Carbon $from = null, ?Carbon $to = null): array
    {
        $q = PageView::query()->select('user_agent');
        if ($from && $to) {
            $q->whereBetween('created_at', [$from, $to]);
        }

        $counts = ['mobile' => 0, 'tablet' => 0, 'desktop' => 0];

        foreach ($q->cursor() as $row) {
            $device = $this->parseDevice((string) $row->user_agent);
            $counts[$device]++;
        }

        return collect($counts)
            ->map(fn ($count, $device) => ['device' => $device, 'count' => $count])
            ->values()
            ->all();
    }

    public function parseDevice(string $ua): string
    {
        $ua = strtolower($ua);
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return 'tablet';
        }
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return 'mobile';
        }

        return 'desktop';
    }

    public function todayCount(): int
    {
        return PageView::query()->where('created_at', '>=', now()->startOfDay())->count();
    }

    public function monthCount(): int
    {
        return PageView::query()->where('created_at', '>=', now()->startOfMonth())->count();
    }
}
