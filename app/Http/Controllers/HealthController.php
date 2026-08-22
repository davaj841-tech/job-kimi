<?php

namespace App\Http\Controllers;

use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(HealthCheckService $health): JsonResponse
    {
        $result = $health->run();

        return response()->json([
            'status' => $result['status'],
            'timestamp' => $result['timestamp'],
            'checks' => $result['checks'],
        ], $result['http_status']);
    }
}
