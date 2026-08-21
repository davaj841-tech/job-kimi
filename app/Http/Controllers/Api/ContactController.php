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
        if ($request->filled('mobile')) {
            $request->merge([
                'mobile' => \App\Support\IranMobile::normalize((string) $request->input('mobile')) ?? $request->input('mobile'),
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'mobile' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'email' => ['required', 'email', 'max:150'],
            'subject' => ['required', 'in:support,complaint,suggestion,partnership'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $tracking = ContactMessage::generateTrackingCode();

        $row = ContactMessage::query()->create([
            'tracking_code' => $tracking,
            'name' => $data['name'],
            'mobile' => $data['mobile'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'open',
        ]);

        $this->mailConfigService->sendContactForm([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'tracking_code' => $row->tracking_code,
        ]);

        return $this->successResponse(
            ['tracking_code' => $row->tracking_code],
            'پیام شما ارسال شد. شماره پیگیری: '.$row->tracking_code
        );
    }
}
