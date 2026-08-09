<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class PublicSettingsController extends BaseController
{
    public function index(): JsonResponse
    {
        $turnstileEnabled = Setting::get('captcha_enabled', 'false') === 'true'
            || Setting::get('turnstile_enabled', 'false') === 'true';

        return $this->successResponse([
            'site_name' => Setting::get('site_name', 'JobAzmoon'),
            'support_email' => Setting::get('support_email', ''),
            'support_phone' => Setting::get('support_phone', ''),
            'onboarding_enabled' => Setting::get('onboarding_enabled', 'true'),
            'primary_color' => Setting::get('primary_color', '#f97316'),
            'instagram_url' => Setting::get('instagram_url', ''),
            'telegram_url' => Setting::get('telegram_url', ''),
            'enamad_url' => Setting::get('enamad_url', ''),
            'samandehi_url' => Setting::get('samandehi_url', ''),
            'turnstile_enabled' => $turnstileEnabled,
            'turnstile_site_key' => $turnstileEnabled ? Setting::get('turnstile_site_key', '') : '',
            'captcha_enabled' => $turnstileEnabled,
        ]);
    }
}
