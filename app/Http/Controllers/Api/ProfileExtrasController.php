<?php

namespace App\Http\Controllers\Api;

use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileExtrasController extends BaseController
{
    public function achievements(Request $request, AchievementService $achievements): JsonResponse
    {
        return $this->successResponse($achievements->forUser($request->user()));
    }

    public function notificationPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->successResponse($user->notification_preferences ?: $this->defaults());
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['nullable', 'array'],
            'sms' => ['nullable', 'array'],
            'push' => ['nullable', 'array'],
        ]);

        $prefs = array_merge($this->defaults(), $request->user()->notification_preferences ?? [], $data);
        $request->user()->update(['notification_preferences' => $prefs]);

        return $this->successResponse($prefs, 'تنظیمات اعلان ذخیره شد.');
    }

    protected function defaults(): array
    {
        $types = [
            'exam_completed', 'subscription_expiring', 'job_post_approved',
            'pdf_purchased', 'wallet_charged', 'admin_reply',
        ];

        return [
            'email' => array_fill_keys($types, true),
            'sms' => array_fill_keys($types, false),
            'push' => array_fill_keys($types, true),
        ];
    }
}
