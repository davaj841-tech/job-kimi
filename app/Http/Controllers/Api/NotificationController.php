<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $unreadOnly = filter_var($request->query('unread'), FILTER_VALIDATE_BOOLEAN);

        $query = $user->notifications()->latest();
        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $items = $query->paginate((int) $request->query('per_page', 20));

        $data = collect($items->items())->map(fn (DatabaseNotification $n) => $this->transform($n));

        return $this->successResponse([
            'data' => $data,
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->successResponse([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return $this->successResponse($this->transform($notification), 'خوانده شد.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->successResponse(null, 'همه اعلان‌ها خوانده شد.');
    }

    protected function transform(DatabaseNotification $n): array
    {
        $data = $n->data ?? [];

        return [
            'id' => $n->id,
            'type' => $data['type'] ?? class_basename($n->type),
            'title' => $data['title'] ?? 'اعلان',
            'message' => $data['message'] ?? '',
            'data' => $data,
            'link' => $data['link'] ?? null,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
            'is_read' => $n->read_at !== null,
        ];
    }
}
