<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Services\Aggregation\AggregationScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AggregationScheduleAdminController extends BaseController
{
    public function __construct(
        protected AggregationScheduleService $schedule,
    ) {}

    public function show(): JsonResponse
    {
        $config = $this->schedule->get();

        return $this->successResponse([
            'schedule' => $config,
            'meta' => [
                'timezone_note' => 'زمان‌بندی از timezone ذخیره‌شده استفاده می‌کند و APP_TIMEZONE را تغییر نمی‌دهد.',
                'app_timezone' => config('app.timezone'),
                'schedule_timezone' => $config['timezone'],
                'current_slot' => $this->schedule->nowSlot(),
                'is_due_now' => $this->schedule->isDueNow(),
                'queue' => config('aggregation.queue', 'crawlers'),
                'max_times' => AggregationScheduleService::MAX_TIMES,
                'server_scheduler_requirement' => '* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1',
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'max_concurrent' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'dispatch_delay_seconds' => ['sometimes', 'integer', 'min:0', 'max:300'],
            'retry_tries' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'times' => ['sometimes', 'array', 'max:'.AggregationScheduleService::MAX_TIMES],
            'times.*.id' => ['nullable', 'string', 'max:64'],
            'times.*.time' => ['required_with:times', 'string', 'max:5'],
            'times.*.enabled' => ['sometimes', 'boolean'],
            'times.*.label' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $updated = $this->schedule->update($data);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse(['schedule' => $updated], 'تنظیمات زمان‌بندی ذخیره شد.');
    }

    public function addTime(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'time' => ['required', 'string', 'max:5'],
            'enabled' => ['sometimes', 'boolean'],
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $config = $this->schedule->get();
        $config['times'][] = [
            'id' => (string) Str::uuid(),
            'time' => $payload['time'],
            'enabled' => $payload['enabled'] ?? true,
            'label' => $payload['label'] ?? null,
        ];

        try {
            $updated = $this->schedule->update(['times' => $config['times']]);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse(['schedule' => $updated], 'زمان اجرا اضافه شد.', 201);
    }

    public function updateTime(Request $request, string $id): JsonResponse
    {
        $payload = $request->validate([
            'time' => ['sometimes', 'string', 'max:5'],
            'enabled' => ['sometimes', 'boolean'],
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $config = $this->schedule->get();
        $found = false;
        foreach ($config['times'] as &$row) {
            if (($row['id'] ?? '') === $id) {
                $found = true;
                if (array_key_exists('time', $payload)) {
                    $row['time'] = $payload['time'];
                }
                if (array_key_exists('enabled', $payload)) {
                    $row['enabled'] = (bool) $payload['enabled'];
                }
                if (array_key_exists('label', $payload)) {
                    $row['label'] = $payload['label'];
                }
            }
        }
        unset($row);

        if (! $found) {
            return $this->errorResponse('زمان یافت نشد.', 404);
        }

        try {
            $updated = $this->schedule->update(['times' => $config['times']]);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse(['schedule' => $updated], 'زمان اجرا به‌روزرسانی شد.');
    }

    public function removeTime(string $id): JsonResponse
    {
        $config = $this->schedule->get();
        $before = count($config['times']);
        $config['times'] = array_values(array_filter(
            $config['times'],
            fn ($row) => ($row['id'] ?? '') !== $id
        ));

        if (count($config['times']) === $before) {
            return $this->errorResponse('زمان یافت نشد.', 404);
        }

        // If removing last enabled time while schedule is on, disable schedule.
        if ($config['enabled'] && collect($config['times'])->where('enabled', true)->isEmpty()) {
            $config['enabled'] = false;
        }

        try {
            $updated = $this->schedule->update($config);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse(['schedule' => $updated], 'زمان اجرا حذف شد.');
    }

    /**
     * Manual dispatch respecting frequency/whitelist; ignores schedule window.
     */
    public function dispatchNow(Request $request): JsonResponse
    {
        $dry = filter_var($request->input('dry_run', false), FILTER_VALIDATE_BOOLEAN);
        $sync = filter_var(
            $request->input('sync', config('aggregation.dispatch_sync', true)),
            FILTER_VALIDATE_BOOLEAN
        );

        $code = Artisan::call('jobs:aggregate-dispatch', [
            '--force' => true,
            '--dry-run' => $dry,
            '--sync' => $sync && ! $dry,
        ]);
        $output = trim(Artisan::output());

        return $this->successResponse([
            'exit_code' => $code,
            'output' => $output,
            'queue' => config('aggregation.queue', 'crawlers'),
            'sync' => $sync && ! $dry,
        ], $dry ? 'Dry-run انجام شد.' : ($sync ? 'خزش هم‌زمان انجام شد.' : 'دیسپاچ در صف قرار گرفت.'));
    }
}
