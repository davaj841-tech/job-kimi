<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketAdminController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::query()->with('user:id,name,mobile')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->query('priority'));
        }
        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('subject', 'like', "%{$s}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('mobile', 'like', "%{$s}%"));
            });
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

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);
        $data = $request->validate([
            'status' => ['nullable', 'in:open,closed'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $ticket->update(array_filter($data, fn ($v) => $v !== null));

        return $this->successResponse($ticket->fresh()->load('user:id,name,mobile'), 'تیکت به‌روزرسانی شد.');
    }
}
