<?php

namespace App\Http\Controllers\Api;

use App\Models\ContactMessage;
use App\Services\MailConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends BaseController
{
    public function __construct(
        protected MailConfigService $mailConfigService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'subject' => ['required', 'in:support,complaint,suggestion,partnership'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $tracking = ContactMessage::generateTrackingCode();

        $row = ContactMessage::query()->create([
            'tracking_code' => $tracking,
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'open',
        ]);

        $this->mailConfigService->sendContactForm([
            ...$data,
            'tracking_code' => $row->tracking_code,
        ]);

        return $this->successResponse(
            ['tracking_code' => $row->tracking_code],
            'پیام شما ارسال شد. شماره پیگیری: '.$row->tracking_code
        );
    }
}
