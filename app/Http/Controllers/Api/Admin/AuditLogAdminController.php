<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogAdminController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->with('user:id,name,mobile')->latest('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }
        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->query('action').'%');
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', 'like', '%'.$request->query('entity_type').'%');
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        $items = $query->paginate((int) $request->query('per_page', 20));

        return $this->successResponse([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }
}
