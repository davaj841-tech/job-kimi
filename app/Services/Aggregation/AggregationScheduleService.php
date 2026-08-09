<?php

namespace App\Services\Aggregation;

use App\Models\JobSource;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Admin-configurable aggregation schedule (global times + concurrency policy).
 * Timezone is stored explicitly and does not change APP_TIMEZONE.
 */
class AggregationScheduleService
{
    public const SETTING_KEY = 'aggregation_schedule';

    public const MAX_TIMES = 24;

    /**
     * @return array{
     *   enabled: bool,
     *   timezone: string,
     *   max_concurrent: int,
     *   dispatch_delay_seconds: int,
     *   retry_tries: int,
     *   times: list<array{id: string, time: string, enabled: bool, label: ?string}>
     * }
     */
    public function get(): array
    {
        $raw = Setting::get(self::SETTING_KEY);
        $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);

        if (! is_array($decoded)) {
            $decoded = [];
        }

        return $this->normalizeConfig($decoded);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(array $input): array
    {
        $current = $this->get();
        $merged = array_merge($current, $input);
        if (isset($input['times']) && is_array($input['times'])) {
            $merged['times'] = $input['times'];
        }

        $normalized = $this->normalizeConfig($merged, validate: true);
        Setting::set(self::SETTING_KEY, json_encode($normalized, JSON_UNESCAPED_UNICODE), 'aggregation');

        return $normalized;
    }

    /**
     * Is aggregation globally enabled?
     */
    public function isEnabled(): bool
    {
        return (bool) $this->get()['enabled'];
    }

    /**
     * Current HH:mm in the configured aggregation timezone.
     */
    public function nowSlot(?Carbon $now = null): string
    {
        $tz = $this->get()['timezone'];
        $now = ($now ?? now())->copy()->timezone($tz);

        return $now->format('H:i');
    }

    /**
     * Whether the current minute matches an enabled schedule slot
     * (global times, or any custom-scheduled dispatchable source).
     */
    public function isDueNow(?Carbon $now = null): bool
    {
        $config = $this->get();
        if (! $config['enabled']) {
            return false;
        }

        $slot = $this->nowSlot($now);
        foreach ($config['times'] as $row) {
            if (($row['enabled'] ?? false) && ($row['time'] ?? '') === $slot) {
                return true;
            }
        }

        return JobSource::query()
            ->dispatchable()
            ->where('schedule_mode', 'custom')
            ->get(['id', 'schedule_mode', 'custom_schedule_times', 'last_crawled_at', 'crawl_frequency'])
            ->contains(fn (JobSource $source) => $this->sourceMatchesSlot($source, $slot));
    }

    /**
     * Enabled global HH:mm values.
     *
     * @return list<string>
     */
    public function enabledGlobalTimes(): array
    {
        return collect($this->get()['times'])
            ->filter(fn ($t) => ($t['enabled'] ?? false) && filled($t['time'] ?? null))
            ->pluck('time')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Should this source run at the given HH:mm slot?
     */
    public function sourceMatchesSlot(JobSource $source, string $slot): bool
    {
        $mode = $source->schedule_mode ?: 'global';
        if ($mode === 'custom') {
            $times = is_array($source->custom_schedule_times) ? $source->custom_schedule_times : [];
            foreach ($times as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (($row['enabled'] ?? true) && ($row['time'] ?? '') === $slot) {
                    return true;
                }
            }

            return false;
        }

        return in_array($slot, $this->enabledGlobalTimes(), true);
    }

    /**
     * Minimum minutes between crawls based on crawl_frequency.
     */
    public function minimumIntervalMinutes(JobSource $source): int
    {
        $freq = Str::lower(trim((string) ($source->crawl_frequency ?: 'daily')));

        return match ($freq) {
            'hourly', 'every_hour' => 60,
            'every_2_hours', '2h' => 120,
            'every_3_hours', '3h' => 180,
            'every_6_hours', '6h' => 360,
            'every_12_hours', 'twice_daily', '12h' => 720,
            'weekly' => 10080,
            'daily' => 1440,
            default => preg_match('/^(\d+)\s*h(ours)?$/', $freq, $m)
                ? max(1, (int) $m[1]) * 60
                : 1440,
        };
    }

    /**
     * Has enough time passed since last crawl for this source?
     */
    public function isSourceDueByFrequency(JobSource $source, ?Carbon $now = null): bool
    {
        $now = $now ?? now();
        if (! $source->last_crawled_at) {
            return true;
        }

        $minutes = $this->minimumIntervalMinutes($source);

        return $source->last_crawled_at->copy()->addMinutes($minutes)->lte($now);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function normalizeConfig(array $config, bool $validate = false): array
    {
        $timezone = (string) ($config['timezone'] ?? 'Asia/Tehran');
        if ($validate && ! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('منطقه زمانی نامعتبر است.');
        }

        $timesIn = is_array($config['times'] ?? null) ? $config['times'] : [];
        if ($validate && count($timesIn) > self::MAX_TIMES) {
            throw new InvalidArgumentException('حداکثر '.self::MAX_TIMES.' زمان اجرا مجاز است.');
        }

        $seen = [];
        $times = [];
        foreach ($timesIn as $row) {
            if (! is_array($row)) {
                continue;
            }
            $time = $this->normalizeTime((string) ($row['time'] ?? ''));
            if ($time === null) {
                if ($validate) {
                    throw new InvalidArgumentException('زمان نامعتبر است. فرمت معتبر HH:MM است.');
                }
                continue;
            }
            if (isset($seen[$time])) {
                if ($validate) {
                    throw new InvalidArgumentException("زمان تکراری: {$time}");
                }
                continue;
            }
            $seen[$time] = true;
            $times[] = [
                'id' => (string) ($row['id'] ?? (string) Str::uuid()),
                'time' => $time,
                'enabled' => (bool) ($row['enabled'] ?? true),
                'label' => filled($row['label'] ?? null) ? Str::limit((string) $row['label'], 80, '') : null,
            ];
        }

        usort($times, fn ($a, $b) => strcmp($a['time'], $b['time']));

        $enabled = (bool) ($config['enabled'] ?? false);
        if ($validate && $enabled && collect($times)->where('enabled', true)->isEmpty()) {
            throw new InvalidArgumentException('برای فعال‌سازی تجمیع، حداقل یک زمان فعال لازم است.');
        }

        return [
            'enabled' => $enabled,
            'timezone' => $timezone,
            'max_concurrent' => max(1, min(20, (int) ($config['max_concurrent'] ?? 5))),
            'dispatch_delay_seconds' => max(0, min(300, (int) ($config['dispatch_delay_seconds'] ?? 0))),
            'retry_tries' => max(1, min(5, (int) ($config['retry_tries'] ?? 2))),
            'times' => array_values($times),
        ];
    }

    protected function normalizeTime(string $value): ?string
    {
        $value = trim($value);
        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $m)) {
            return null;
        }

        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }
}
