<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsAdminController extends BaseController
{
    public function __construct(protected AnalyticsService $analytics) {}

    public function visits(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'group_by' => ['nullable', 'in:day,hour'],
        ]);

        return $this->successResponse(
            $this->analytics->visits(
                $data['date_from'] ?? null,
                $data['date_to'] ?? null,
                $data['group_by'] ?? 'day'
            )
        );
    }

    public function topPages(Request $request): JsonResponse
    {
        $from = $request->query('date_from') ? Carbon::parse($request->query('date_from'))->startOfDay() : null;
        $to = $request->query('date_to') ? Carbon::parse($request->query('date_to'))->endOfDay() : null;

        return $this->successResponse($this->analytics->topPages(10, $from, $to));
    }

    public function devices(Request $request): JsonResponse
    {
        $from = $request->query('date_from') ? Carbon::parse($request->query('date_from'))->startOfDay() : null;
        $to = $request->query('date_to') ? Carbon::parse($request->query('date_to'))->endOfDay() : null;

        return $this->successResponse($this->analytics->devices($from, $to));
    }
}
