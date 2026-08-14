<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        // تمام تنظیمات درگاه و SMS از اینجا قابل پیکربندی‌اند — بدون هاردکد
        $settings = [
            ['key' => 'sms_gateway', 'value' => 'kavenegar', 'group' => 'sms'],
            ['key' => 'sms_api_key', 'value' => '', 'group' => 'sms'],
            ['key' => 'payment_gateway', 'value' => 'zarinpal', 'group' => 'payment'],
            ['key' => 'zarinpal_merchant_id', 'value' => '', 'group' => 'payment'],
            ['key' => 'zarinpal_sandbox', 'value' => filter_var(env('ZARINPAL_SANDBOX', false), FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false', 'group' => 'payment'],
            ['key' => 'nextpay_api_key', 'value' => '', 'group' => 'payment'],
            ['key' => 'nextpay_active', 'value' => 'false', 'group' => 'payment'],
            ['key' => 'idpay_api_key', 'value' => '', 'group' => 'payment'],
            ['key' => 'idpay_active', 'value' => 'false', 'group' => 'payment'],
            ['key' => 'idpay_sandbox', 'value' => filter_var(env('IDPAY_SANDBOX', false), FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false', 'group' => 'payment'],
            ['key' => 'min_wallet_charge', 'value' => '10000', 'group' => 'payment'],
            ['key' => 'site_name', 'value' => 'JobAzmoon', 'group' => 'general'],
            ['key' => 'homepage_layout', 'value' => 'atlas', 'group' => 'homepage'],
            ['key' => 'site_font', 'value' => 'estedad', 'group' => 'homepage'],
            ['key' => 'site_font_size', 'value' => '16', 'group' => 'homepage'],
            ['key' => 'default_exam_duration', 'value' => '60', 'group' => 'exam'],
            ['key' => 'ai_daily_limit', 'value' => '50', 'group' => 'ai'],
            ['key' => 'ai_enabled', 'value' => 'true', 'group' => 'ai'],
            ['key' => 'ai_model', 'value' => 'gpt-4', 'group' => 'ai'],
            ['key' => 'ai_api_key', 'value' => '', 'group' => 'ai'],
            ['key' => 'ai_job_crawl_sources', 'value' => '[]', 'group' => 'ai'],
            ['key' => 'ai_resume_limit_per_day', 'value' => '5', 'group' => 'ai'],
            ['key' => 'ai_question_limit_per_day', 'value' => '20', 'group' => 'ai'],
            ['key' => 'free_plan_exam_limit', 'value' => '5', 'group' => 'subscription'],
            ['key' => 'onboarding_enabled', 'value' => 'true', 'group' => 'general'],
            ['key' => 'smtp_host', 'value' => '', 'group' => 'mail'],
            ['key' => 'smtp_port', 'value' => '587', 'group' => 'mail'],
            ['key' => 'smtp_username', 'value' => '', 'group' => 'mail'],
            ['key' => 'smtp_password', 'value' => '', 'group' => 'mail'],
            ['key' => 'smtp_from_address', 'value' => 'noreply@jobazmoon.ir', 'group' => 'mail'],
            ['key' => 'smtp_from_name', 'value' => 'جاب‌آزمون', 'group' => 'mail'],
        ];

        foreach ($settings as $setting) {
            Setting::query()->updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
