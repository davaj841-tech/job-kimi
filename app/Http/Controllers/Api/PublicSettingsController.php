<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
use App\Services\Seo\SeoManager;
use App\Support\PublicAsset;
use App\Support\SiteFonts;
use App\Support\SiteThemes;
use Illuminate\Http\JsonResponse;

class PublicSettingsController extends BaseController
{
    public function index(): JsonResponse
    {
        $payload = cache()->remember('public_settings_payload', 60, function () {
            $siteKey = (string) Setting::getFilled(
                'turnstile_site_key',
                config('services.turnstile.site_key', '')
            );
            $secret = (string) Setting::getFilled(
                'turnstile_secret_key',
                config('services.turnstile.secret', '')
            );
            // Prefer Turnstile when both keys exist; otherwise math captcha
            $captchaMode = ($siteKey !== '' && $secret !== '') ? 'turnstile' : 'math';

            $siteLogo = PublicAsset::url((string) Setting::get('site_logo', ''));

            return [
                'site_name' => Setting::get('site_name', 'جاب‌آزمون'),
                'site_logo' => $siteLogo,
                'logo_dark' => $siteLogo,
                'logo_mobile' => $siteLogo,
                'site_favicon' => PublicAsset::url((string) Setting::get('site_favicon', '')),
                'support_email' => Setting::get('support_email', ''),
                'support_phone' => Setting::get('support_phone', ''),
                'onboarding_enabled' => Setting::get('onboarding_enabled', 'true'),
                'primary_color' => SiteThemes::sanitizeHex(Setting::get('primary_color', '#f97316'), '#f97316'),
                'secondary_color' => SiteThemes::sanitizeHex(Setting::get('secondary_color', '#0f2744'), '#0f2744'),
                'homepage_layout' => SiteThemes::normalize(Setting::get('homepage_layout', SiteThemes::DEFAULT)),
                'site_font' => SiteFonts::normalize(Setting::get('site_font', SiteFonts::DEFAULT)),
                'site_font_size' => SiteFonts::sanitizeSize(Setting::get('site_font_size', 16)),
                'exam_questions_per_page' => max(1, min(20, (int) Setting::get('exam_questions_per_page', 5))),
                'instagram_url' => Setting::get('instagram_url', ''),
                'telegram_url' => Setting::get('telegram_url', ''),
                'whatsapp_url' => Setting::get('whatsapp_url', ''),
                'rubika_url' => Setting::get('rubika_url', ''),
                'bale_url' => Setting::get('bale_url', ''),
                'enamad_url' => Setting::get('enamad_url', ''),
                'samandehi_url' => Setting::get('samandehi_url', ''),
                'android_play_url' => Setting::get('android_play_url', ''),
                'android_bazaar_url' => Setting::get('android_bazaar_url', ''),
                'android_direct_url' => PublicAsset::url((string) Setting::get('android_direct_url', '')),
                'captcha_mode' => $captchaMode,
                'turnstile_enabled' => $captchaMode === 'turnstile',
                'turnstile_site_key' => $captchaMode === 'turnstile' ? $siteKey : '',
                'captcha_enabled' => true,
                'seo' => app(SeoManager::class)->buildHomePayload(),
            ];
        });

        return $this->successResponse($payload)->header('Cache-Control', 'public, max-age=30');
    }
}
