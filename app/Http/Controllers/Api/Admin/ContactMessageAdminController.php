<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Mail\ContactReplyMail;
use App\Models\ContactMessage;
use App\Services\MailConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactMessageAdminController extends BaseController
{
    public function __construct(
        protected MailConfigService $mailConfigService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ContactMessage::query()->with('replier:id,name')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('tracking_code', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('mobile', 'like', "%{$s}%")
                    ->orWhere('message', 'like', "%{$s}%");
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

    public function show(int $id): JsonResponse
    {
        $row = ContactMessage::query()->with('replier:id,name')->findOrFail($id);

        return $this->successResponse($row);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $row = ContactMessage::query()->findOrFail($id);
        $data = $request->validate([
            'reply' => ['required', 'string', 'max:5000'],
        ]);

        $row->update([
            'reply' => $data['reply'],
            'replied_at' => now(),
            'replied_by' => $request->user()?->id,
            'status' => 'replied',
        ]);

        if (! filter_var((string) $row->email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('پاسخ ذخیره شد اما ایمیل معتبری برای ارسال ثبت نشده است.', 422);
        }

        try {
            $this->mailConfigService->sendNow($row->email, new ContactReplyMail(
                $row->name,
                $row->tracking_code,
                $data['reply']
            ));
        } catch (\Throwable $e) {
            return $this->errorResponse(
                'پاسخ ذخیره شد اما ارسال ایمیل ناموفق بود. تنظیمات SMTP را بررسی کنید.',
                422
            );
        }

        return $this->successResponse($row->fresh()->load('replier:id,name'), 'پاسخ به ایمیل کاربر ارسال شد.');
    }
}
