<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Services\AuditLogService;
use App\Services\Payment\GatewayCredentialsResolver;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentGatewayAdminController extends BaseController
{
    public function __construct(
        protected PaymentGatewayManager $manager,
        protected GatewayCredentialsResolver $credentials,
        protected AuditLogService $audit,
    ) {}

    public function index(): JsonResponse
    {
        $this->ensureRows();

        $default = $this->manager->defaultName();
        $rows = PaymentGateway::query()->orderBy('sort_order')->get()->keyBy('name');

        $items = [];
        foreach ($this->manager->catalog() as $meta) {
            $code = $meta['code'];
            /** @var PaymentGateway|null $row */
            $row = $rows->get($code);
            $driver = $this->manager->driver($code);
            $fields = $this->fieldSchema($code);
            $secretKeys = collect($fields)->where('secret', true)->pluck('key')->all();
            $settingsPlain = $this->credentials->decryptSettings(is_array($row?->settings) ? $row->settings : []);
            foreach ($fields as $field) {
                $key = $field['key'];
                $column = $field['column'] ?? null;
                if ($column === 'merchant_id' && filled($row?->merchant_id) && ! isset($settingsPlain[$key])) {
                    $settingsPlain[$key] = (string) $row->merchant_id;
                }
                if ($column === 'api_key' && filled($row?->api_key) && ! isset($settingsPlain[$key])) {
                    $settingsPlain[$key] = (string) $row->api_key;
                }
            }
            $settings = $this->credentials->maskForAdmin($settingsPlain, $secretKeys);

            // Never return plaintext credentials in admin API responses.
            $items[] = [
                'name' => $code,
                'display_name' => $row?->display_name ?: $meta['display_name'],
                'is_active' => (bool) ($row?->is_active ?? false),
                'is_default' => $code === $default,
                'sort_order' => (int) ($row?->sort_order ?? 99),
                'configured' => $driver->isConfigured(),
                'merchant_id' => filled($row?->merchant_id)
                    ? $this->credentials->maskSecret((string) $row->merchant_id)
                    : null,
                'api_key' => filled($row?->api_key)
                    ? $this->credentials->maskSecret((string) $row->api_key)
                    : null,
                'settings' => $settings,
                'fields' => $fields,
            ];
        }

        return $this->successResponse([
            'default' => $default,
            'gateways' => $items,
        ]);
    }

    public function update(Request $request, string $name): JsonResponse
    {
        if (! $this->manager->isOnlineGateway($name)) {
            return $this->errorResponse('درگاه نامعتبر است.', 404);
        }

        $this->ensureRows();
        $fields = $this->fieldSchema($name);
        $fieldKeys = collect($fields)->pluck('key')->all();
        $secretKeys = collect($fields)->where('secret', true)->pluck('key')->all();

        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'merchant_id' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:2000'],
            'settings' => ['nullable', 'array'],
            'settings.*' => ['nullable', 'string', 'max:8000'],
        ]);

        /** @var PaymentGateway $row */
        $row = PaymentGateway::query()->where('name', $name)->firstOrFail();

        $settingsInput = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $settingsInput = array_intersect_key($settingsInput, array_flip($fieldKeys));

        $existing = $this->credentials->decryptSettings($row->settings ?? []);
        foreach ($settingsInput as $key => $value) {
            if (! is_string($value) || $this->credentials->isMaskedPlaceholder($value)) {
                continue;
            }
            $existing[$key] = $value;
        }

        $row->display_name = $data['display_name'] ?? $row->display_name;
        if (array_key_exists('is_active', $data)) {
            $row->is_active = (bool) $data['is_active'];
        }
        if (array_key_exists('sort_order', $data)) {
            $row->sort_order = (int) $data['sort_order'];
        }

        if (array_key_exists('merchant_id', $data) && is_string($data['merchant_id'])
            && ! $this->credentials->isMaskedPlaceholder($data['merchant_id'])) {
            $row->merchant_id = $data['merchant_id'] !== '' ? $data['merchant_id'] : null;
        }
        if (array_key_exists('api_key', $data) && is_string($data['api_key'])
            && ! $this->credentials->isMaskedPlaceholder($data['api_key'])) {
            $row->api_key = $data['api_key'] !== '' ? $data['api_key'] : null;
        }

        $this->applyColumnAliases($name, $row, $existing);

        $row->settings = $this->credentials->encryptSettings($existing, $secretKeys);
        $row->save();

        $this->mirrorLegacySettings($name, $row, $existing);

        if ($row->is_default && ! $row->is_active) {
            $row->update(['is_default' => false]);
            $fallback = PaymentGateway::query()->active()->orderBy('sort_order')->first();
            if ($fallback) {
                $fallback->update(['is_default' => true]);
                Setting::set('payment_gateway', $fallback->name);
            }
        }

        $this->audit->log('payment_gateway.updated', $row, null, [
            'name' => $name,
            'is_active' => $row->is_active,
        ]);

        return $this->index();
    }

    public function setDefault(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', Rule::in($this->manager->registeredCodes())],
        ]);

        $this->ensureRows();

        $row = PaymentGateway::query()->where('name', $data['name'])->firstOrFail();
        if (! $row->is_active) {
            return $this->errorResponse('فقط درگاه فعال را می‌توان پیش‌فرض کرد.', 422);
        }

        $driver = $this->manager->driver($row->name);
        if (! $driver->isConfigured()) {
            return $this->errorResponse('اطلاعات اتصال این درگاه کامل نشده است.', 422);
        }

        DB::transaction(function () use ($row) {
            PaymentGateway::query()->update(['is_default' => false]);
            $row->update(['is_default' => true]);
            Setting::set('payment_gateway', $row->name);
        });

        $this->audit->log('payment_gateway.default', $row, null, ['name' => $row->name]);

        return $this->index();
    }

    public function test(string $name): JsonResponse
    {
        if (! $this->manager->isOnlineGateway($name)) {
            return $this->errorResponse('درگاه نامعتبر است.', 404);
        }

        $result = $this->manager->driver($name)->testConnection();

        return $this->successResponse($result, $result['message'] ?? 'نتیجه تست');
    }

    protected function ensureRows(): void
    {
        $sort = 1;
        foreach ($this->manager->catalog() as $meta) {
            PaymentGateway::query()->firstOrCreate(
                ['name' => $meta['code']],
                [
                    'display_name' => $meta['display_name'],
                    'is_active' => $meta['code'] === 'zarinpal',
                    'is_default' => $meta['code'] === 'zarinpal',
                    'sort_order' => $sort++,
                ]
            );
        }
    }

    /**
     * @return list<array{key: string, label: string, secret: bool, column?: string}>
     */
    protected function fieldSchema(string $code): array
    {
        return match ($code) {
            'zarinpal' => [
                ['key' => 'merchant_id', 'label' => 'Merchant ID', 'secret' => true],
                ['key' => 'sandbox', 'label' => 'حالت آزمایشی (sandbox)', 'secret' => false],
            ],
            'nextpay' => [
                ['key' => 'api_key', 'label' => 'API Key', 'secret' => true, 'column' => 'api_key'],
            ],
            'idpay' => [
                ['key' => 'api_key', 'label' => 'API Key', 'secret' => true, 'column' => 'api_key'],
                ['key' => 'sandbox', 'label' => 'حالت آزمایشی', 'secret' => false],
            ],
            'mellat', 'shaparak' => [
                ['key' => 'terminal_id', 'label' => 'Terminal ID', 'secret' => false, 'column' => 'merchant_id'],
                ['key' => 'username', 'label' => 'Username', 'secret' => false, 'column' => 'api_key'],
                ['key' => 'password', 'label' => 'Password', 'secret' => true],
            ],
            'parsian' => [
                ['key' => 'pin', 'label' => 'LoginAccount / PIN', 'secret' => true],
            ],
            'saman' => [
                ['key' => 'terminal_id', 'label' => 'Terminal ID', 'secret' => false, 'column' => 'merchant_id'],
            ],
            'pasargad' => [
                ['key' => 'merchant_code', 'label' => 'Merchant Code', 'secret' => false, 'column' => 'merchant_id'],
                ['key' => 'terminal_code', 'label' => 'Terminal Code', 'secret' => false],
                ['key' => 'private_key', 'label' => 'Private Key (PEM)', 'secret' => true],
            ],
            'sadad' => [
                ['key' => 'merchant_id', 'label' => 'Merchant ID', 'secret' => true],
                ['key' => 'terminal_id', 'label' => 'Terminal ID', 'secret' => false],
                ['key' => 'terminal_key', 'label' => 'Terminal Key', 'secret' => true],
            ],
            'ap' => [
                ['key' => 'username', 'label' => 'Username', 'secret' => false, 'column' => 'api_key'],
                ['key' => 'password', 'label' => 'Password', 'secret' => true],
                ['key' => 'merchant_config_id', 'label' => 'Merchant Config ID', 'secret' => false, 'column' => 'merchant_id'],
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function applyColumnAliases(string $name, PaymentGateway $row, array &$settings): void
    {
        foreach ($this->fieldSchema($name) as $field) {
            $key = $field['key'];
            $column = $field['column'] ?? null;
            // Secrets stay in encrypted settings JSON only — never plaintext columns.
            if (! $column || ($field['secret'] ?? false) || ! isset($settings[$key])) {
                continue;
            }
            $value = $settings[$key];
            if (! is_string($value) || $this->credentials->isMaskedPlaceholder($value)) {
                continue;
            }
            if ($column === 'merchant_id') {
                $row->merchant_id = $value !== '' ? $value : null;
            }
            if ($column === 'api_key') {
                $row->api_key = $value !== '' ? $value : null;
            }
        }
    }

    /**
     * Keep legacy non-secret Setting flags in sync for older screens.
     * Never mirror passwords / API keys / PINs into the settings table.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function mirrorLegacySettings(string $name, PaymentGateway $row, array $settings): void
    {
        $map = match ($name) {
            'zarinpal' => [
                'zarinpal_sandbox' => $settings['sandbox'] ?? null,
            ],
            'idpay' => [
                'idpay_sandbox' => $settings['sandbox'] ?? null,
            ],
            default => [],
        };

        foreach ($map as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_string($value) && $this->credentials->isMaskedPlaceholder($value)) {
                continue;
            }
            Setting::set($key, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
        }

        Setting::set($name.'_active', $row->is_active ? 'true' : 'false');
    }
}
