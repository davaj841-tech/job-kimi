<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
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

        $this->mailConfigService->sendContactForm($data);

        return $this->successResponse(null, 'پیام شما ارسال شد.');
    }
}
