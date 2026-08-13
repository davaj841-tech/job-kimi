<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Services\AuditLogService;
use App\Support\PublicAsset;
use App\Support\SiteFonts;
use App\Support\SiteThemes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsAdminController extends BaseController
{
    /** Keys allowed for admin SPA (grouped). */
    protected array $schema = [
        'general' => [
            'site_name',
            'site_description',
            'site_logo',
            'site_favicon',
            'support_email',
            'support_phone',
            'onboarding_enabled',
            'popular_searches',
            'blog_comments_require_approval',
        ],
        'mail' => [
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_from_address',
            'smtp_from_name',
        ],
        'theme' => [
            'primary_color',
            'secondary_color',
            'logo_dark',
            'logo_light',
        ],
        'homepage' => [
            'homepage_layout',
            'site_font',
        ],
        'seo' => [
            'meta_title',
            'meta_description',
            'meta_keywords',
            'google_analytics_id',
            'google_tag_manager',
        ],
        'payment' => [
            'payment_gateway',
            'zarinpal_merchant_id',
            'zarinpal_sandbox',
            'nextpay_api_key',
            'nextpay_active',
            'idpay_api_key',
            'idpay_active',
            'idpay_sandbox',
            'min_wallet_charge',
        ],
        'sms' => [
            'sms_gateway',
            'sms_api_key',
            'sms_otp_template',
            'sms_subscription_reminder_template',
        ],
        'ai' => [
            'ai_enabled',
            'ai_provider',
            'ai_api_key',
            'ai_model',
            'ai_daily_limit',
            'ai_question_limit_per_day',
            'ai_resume_limit_per_day',
            'ai_blog_enabled',
            'ai_questions_enabled',
            'ai_crawl_enabled',
        ],
        'security' => [
            'turnstile_site_key',
            'turnstile_secret_key',
            'turnstile_enabled',
            'captcha_enabled',
        ],
        'social' => [
            'instagram_url',
            'telegram_url',
            'linkedin_url',
            'enamad_url',
            'samandehi_url',
        ],
        'subscription' => [
            'free_plan_exam_limit',
        ],
        'exam' => [
            'default_exam_duration',
            'exam_questions_per_page',
        ],
    ];

    public function index(Request $request): JsonResponse
    {
        $group = $request->query('group');
        $rows = Setting::query()
            ->when($group, fn ($q) => $q->where('group', $group))
            ->orderBy('group')
            ->orderBy('key')
            ->get(['key', 'value', 'group']);

        $byGroup = [];
        foreach ($this->schema as $g => $keys) {
            if ($group && $g !== $group) {
                continue;
            }
            $byGroup[$g] = [];
            foreach ($keys as $key) {
                $row = $rows->firstWhere('key', $key);
                $value = $row?->value ?? $this->defaultFor($key);
                if (in_array($key, ['site_logo', 'site_favicon', 'logo_light', 'logo_dark'], true)) {
                    $value = PublicAsset::url((string) $value);
                }
                $byGroup[$g][$key] = $value;
            }
        }

        // Include any extra DB keys not in schema
        foreach ($rows as $row) {
            $g = $row->group ?: 'general';
            if (! isset($byGroup[$g])) {
                $byGroup[$g] = [];
            }
            if (! array_key_exists($row->key, $byGroup[$g])) {
                $byGroup[$g][$row->key] = $row->value;
            }
        }

        return $this->successResponse([
            'groups' => $byGroup,
            'schema' => $this->schema,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group' => ['required', 'string', 'max:50'],
            'values' => ['required', 'array'],
        ]);

        $group = $data['group'];
        $allowed = $this->schema[$group] ?? null;

        if (! $allowed) {
            return $this->errorResponse('گروه تنظیمات نامعتبر است.', 422);
        }

        foreach ($data['values'] as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                continue;
            }
            if ($key === 'homepage_layout') {
                $value = SiteThemes::normalize($value);
                Setting::set('homepage_layout', $value, 'homepage');
                $palette = SiteThemes::colors($value);
                Setting::set('primary_color', $palette['primary'], 'theme');
                Setting::set('secondary_color', $palette['secondary'], 'theme');

                continue;
            }
            if ($key === 'site_font') {
                $value = SiteFonts::normalize($value);
            }
            if ($key === 'exam_questions_per_page') {
                $value = (string) max(1, min(20, (int) $value));
            }
            if ($key === 'primary_color') {
                $value = SiteThemes::sanitizeHex($value, '#f97316');
            }
            if ($key === 'secondary_color') {
                $value = SiteThemes::sanitizeHex($value, '#0f2744');
            }
            // Don't wipe uploaded logos when saving other theme fields with empty strings
            if (in_array($key, ['site_logo', 'site_favicon', 'logo_light', 'logo_dark'], true)) {
                if ($value === null || $value === '') {
                    continue;
                }
                $value = PublicAsset::url((string) $value);
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            Setting::set((string) $key, $value === null ? '' : (string) $value, $group);
        }

        if ($group === 'payment') {
            $this->syncPaymentGateways($data['values']);
        }

        app(AuditLogService::class)->log('settings.updated', null, null, [
            'group' => $group,
            'keys' => array_keys($data['values']),
        ]);

        $request->query->set('group', $group);

        return $this->index($request);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,svg,ico', 'max:2048'],
            'type' => ['required', 'in:logo,favicon,logo_dark,logo_light'],
        ]);

        $map = [
            'logo' => ['key' => 'site_logo', 'group' => 'general'],
            'favicon' => ['key' => 'site_favicon', 'group' => 'general'],
            'logo_dark' => ['key' => 'logo_dark', 'group' => 'theme'],
            'logo_light' => ['key' => 'logo_light', 'group' => 'theme'],
        ];

        $target = $map[$data['type']];
        $path = $request->file('file')->store('settings', 'public');
        // Root-relative URL so preview works regardless of APP_URL host
        $url = PublicAsset::url($path);

        Setting::set($target['key'], $url, $target['group']);

        return $this->successResponse([
            'key' => $target['key'],
            'url' => $url,
            'group' => $target['group'],
        ], 'فایل آپلود شد.');
    }

    protected function defaultFor(string $key): string
    {
        return match ($key) {
            'site_name' => 'JobAzmoon',
            'onboarding_enabled' => 'true',
            'smtp_port' => '587',
            'smtp_from_name' => 'جاب‌آزمون',
            'primary_color' => '#f97316',
            'secondary_color' => '#0f2744',
            'homepage_layout' => 'atlas',
            'site_font' => 'estedad',
            'payment_gateway' => 'zarinpal',
            'zarinpal_sandbox' => 'false',
            'nextpay_active' => 'false',
            'idpay_active' => 'false',
            'idpay_sandbox' => 'true',
            'min_wallet_charge' => '10000',
            'sms_gateway' => 'kavenegar',
            'ai_enabled' => 'true',
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4',
            'ai_daily_limit' => '50',
            'ai_question_limit_per_day' => '20',
            'ai_resume_limit_per_day' => '5',
            'ai_blog_enabled', 'ai_questions_enabled', 'ai_crawl_enabled' => 'true',
            'captcha_enabled' => 'false',
            'turnstile_enabled' => 'false',
            'free_plan_exam_limit' => '5',
            'default_exam_duration' => '60',
            'exam_questions_per_page' => '5',
            default => '',
        };
    }

    protected function syncPaymentGateways(array $values): void
    {
        $default = $values['payment_gateway'] ?? Setting::get('payment_gateway', 'zarinpal');

        PaymentGateway::query()->updateOrCreate(
            ['name' => 'zarinpal'],
            [
                'display_name' => 'زرین‌پال',
                'merchant_id' => $values['zarinpal_merchant_id'] ?? Setting::get('zarinpal_merchant_id'),
                'is_active' => true,
                'is_default' => $default === 'zarinpal',
                'sort_order' => 1,
            ]
        );

        PaymentGateway::query()->updateOrCreate(
            ['name' => 'nextpay'],
            [
                'display_name' => 'نکست‌پی',
                'api_key' => $values['nextpay_api_key'] ?? Setting::get('nextpay_api_key'),
                'is_active' => filter_var($values['nextpay_active'] ?? Setting::get('nextpay_active', 'false'), FILTER_VALIDATE_BOOLEAN),
                'is_default' => $default === 'nextpay',
                'sort_order' => 2,
            ]
        );

        PaymentGateway::query()->updateOrCreate(
            ['name' => 'idpay'],
            [
                'display_name' => 'آیدی‌پی',
                'api_key' => $values['idpay_api_key'] ?? Setting::get('idpay_api_key'),
                'is_active' => filter_var($values['idpay_active'] ?? Setting::get('idpay_active', 'false'), FILTER_VALIDATE_BOOLEAN),
                'is_default' => $default === 'idpay',
                'sort_order' => 3,
            ]
        );

        if ($default) {
            PaymentGateway::query()->where('name', '!=', $default)->update(['is_default' => false]);
            PaymentGateway::query()->where('name', $default)->update(['is_default' => true]);
        }
    }
}
