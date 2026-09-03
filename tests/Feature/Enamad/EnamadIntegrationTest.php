<?php

namespace Tests\Feature\Enamad;

use App\Models\Setting;
use App\Models\User;
use App\Support\LegalPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnamadIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        LegalPages::ensure();
    }

    public function test_public_settings_hide_enamad_when_disabled(): void
    {
        Setting::set('enamad_enabled', 'false', 'trust');

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('data.enamad_enabled', false)
            ->assertJsonPath('data.enamad_url', '')
            ->assertJsonPath('data.enamad_logo_url', '');
    }

    public function test_public_settings_expose_official_enamad_urls_when_configured(): void
    {
        Setting::set('enamad_enabled', 'true', 'trust');
        Setting::set('enamad_id', '123456', 'trust');
        Setting::set('enamad_code', 'AbCdEfGhIjKlMnOp', 'trust');

        $response = $this->getJson('/api/v1/settings/public')->assertOk();

        $response
            ->assertJsonPath('data.enamad_enabled', true)
            ->assertJsonPath(
                'data.enamad_url',
                'https://trustseal.enamad.ir/?id=123456&Code=AbCdEfGhIjKlMnOp'
            )
            ->assertJsonPath(
                'data.enamad_logo_url',
                'https://trustseal.enamad.ir/logo.aspx?id=123456&Code=AbCdEfGhIjKlMnOp'
            );

        $url = (string) $response->json('data.enamad_url');
        $this->assertStringStartsWith('https://trustseal.enamad.ir/', $url);
        $this->assertStringNotContainsString('localhost', $url);
    }

    public function test_admin_can_save_trust_settings_from_official_url(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/settings', [
            'group' => 'trust',
            'values' => [
                'enamad_enabled' => true,
                'enamad_url' => 'https://trustseal.enamad.ir/?id=999888&Code=ZzYyXxWwVvUuTtSsRrQqPpOo',
            ],
        ])->assertOk();

        $this->assertSame('999888', Setting::get('enamad_id'));
        $this->assertSame('ZzYyXxWwVvUuTtSsRrQqPpOo', Setting::get('enamad_code'));
    }

    public function test_refund_legal_page_is_public(): void
    {
        $this->getJson('/api/v1/pages/refund')
            ->assertOk()
            ->assertJsonPath('data.slug', 'refund')
            ->assertJsonPath('data.is_published', true);
    }
}
