<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
use App\Support\PublicAsset;
use App\Support\SiteFonts;
use App\Support\SiteThemes;
use Illuminate\Http\JsonResponse;

class PublicSettingsController extends BaseController
{
    public function index(): JsonResponse
    {
        $payload = cache()->remember('public_settings_payload', 60, function () {
            $turnstileEnabled = Setting::get('captcha_enabled', 'false') === 'true'
                || Setting::get('turnstile_enabled', 'false') === 'true'
                || filled(config('services.turnstile.secret'));

            return [
                'site_name' => Setting::get('site_name', 'جاب‌آزمون'),
                'site_logo' => PublicAsset::url((string) Setting::get('site_logo', '')),
                'logo_light' => PublicAsset::url((string) Setting::get('logo_light', '')),
                'logo_dark' => PublicAsset::url((string) Setting::get('logo_dark', '')),
                'site_favicon' => PublicAsset::url((string) Setting::get('site_favicon', '')),
                'support_email' => Setting::get('support_email', ''),
                'support_phone' => Setting::get('support_phone', ''),
                'onboarding_enabled' => Setting::get('onboarding_enabled', 'true'),
                'primary_color' => SiteThemes::sanitizeHex(Setting::get('primary_color', '#f97316'), '#f97316'),
                'secondary_color' => SiteThemes::sanitizeHex(Setting::get('secondary_color', '#0f2744'), '#0f2744'),
                'homepage_layout' => SiteThemes::normalize(Setting::get('homepage_layout', SiteThemes::DEFAULT)),
                'site_font' => SiteFonts::normalize(Setting::get('site_font', SiteFonts::DEFAULT)),
                'exam_questions_per_page' => max(1, min(20, (int) Setting::get('exam_questions_per_page', 5))),
                'instagram_url' => Setting::get('instagram_url', ''),
                'telegram_url' => Setting::get('telegram_url', ''),
                'enamad_url' => Setting::get('enamad_url', ''),
                'samandehi_url' => Setting::get('samandehi_url', ''),
                'turnstile_enabled' => $turnstileEnabled,
                'turnstile_site_key' => $turnstileEnabled
                    ? (string) Setting::getFilled(
                        'turnstile_site_key',
                        config('services.turnstile.site_key', '')
                    )
                    : '',
                'captcha_enabled' => $turnstileEnabled,
            ];
        });

        return $this->successResponse($payload)->header('Cache-Control', 'public, max-age=30');
    }
}
