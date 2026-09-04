<?php

namespace App\Services\Aggregation;

use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pushes aggregation health alerts into admin database notifications.
 * Dedupes with cache (no extra tables / no destructive migrations).
 */
class AggregationAlertNotifier
{
    public function __construct(
        protected AggregationHealthService $health,
    ) {}

    /**
     * @return array{sent: int, skipped: int, alerts: list<string>}
     */
    public function notifyAdmins(?array $snapshot = null): array
    {
        $snapshot ??= $this->health->snapshot();
        /** @var list<array<string, mixed>> $alerts */
        $alerts = is_array($snapshot['alerts'] ?? null) ? $snapshot['alerts'] : [];

        $sent = 0;
        $skipped = 0;
        $codes = [];

        if ($alerts === [] || ! Schema::hasTable('notifications')) {
            return ['sent' => 0, 'skipped' => 0, 'alerts' => []];
        }

        $admins = User::query()
            ->whereIn('role', [StaffRoles::SUPER_ADMIN, StaffRoles::ADMIN])
            ->where('status', 'active')
            ->get();

        if ($admins->isEmpty()) {
            return ['sent' => 0, 'skipped' => 0, 'alerts' => []];
        }

        $cooldown = max(5, (int) config('aggregation.alerts.notify_cooldown_minutes', 360));

        foreach ($alerts as $alert) {
            $code = (string) ($alert['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $codes[] = $code;
            $lockKey = 'aggregation.alert.notify.'.$code;
            if (! Cache::add($lockKey, 1, now()->addMinutes($cooldown))) {
                $skipped++;

                continue;
            }

            try {
                Notification::sendNow(
                    $admins,
                    new GenericDatabaseNotification(
                        type: 'aggregation_alert',
                        title: (string) ($alert['title'] ?? 'هشدار تجمیع آگهی'),
                        message: (string) ($alert['message'] ?? ''),
                        link: (string) ($alert['link'] ?? '/admin/dashboard'),
                        extra: [
                            'code' => $code,
                            'severity' => (string) ($alert['severity'] ?? 'warn'),
                            'source' => 'aggregation_health',
                        ],
                    )
                );
                $sent++;
            } catch (Throwable $e) {
                Cache::forget($lockKey);
                Log::warning('Aggregation alert notify failed', [
                    'code' => $code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'alerts' => array_values(array_unique($codes)),
        ];
    }
}
