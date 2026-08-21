<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomepageLayoutSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_settings_default_to_atlas_layout(): void
    {
        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('data.homepage_layout', 'atlas');
    }

    public function test_public_settings_expose_saved_homepage_layout(): void
    {
        Setting::set('homepage_layout', 'midnight', 'homepage');

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('data.homepage_layout', 'midnight');
    }

    public function test_invalid_homepage_layout_falls_back_to_atlas(): void
    {
        Setting::set('homepage_layout', 'unknown-theme', 'homepage');

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('data.homepage_layout', 'atlas');
    }

    public function test_public_settings_expose_theme_colors(): void
    {
        Setting::set('primary_color', '#06b6d4', 'homepage');
        Setting::set('secondary_color', '#0e7490', 'homepage');

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('data.primary_color', '#06b6d4')
            ->assertJsonPath('data.secondary_color', '#0e7490');
    }

    public function test_saving_homepage_theme_syncs_palette_colors(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'status' => 'active']));

        $this->putJson('/api/v1/admin/settings', [
            'group' => 'homepage',
            'values' => ['homepage_layout' => 'ocean'],
        ])->assertOk();

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('data.homepage_layout', 'ocean')
            ->assertJsonPath('data.primary_color', '#06b6d4')
            ->assertJsonPath('data.secondary_color', '#0e7490');
    }

    public function test_saving_custom_theme_colors(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'status' => 'active']));

        $this->putJson('/api/v1/admin/settings', [
            'group' => 'homepage',
            'values' => [
                'primary_color' => '#22c55e',
                'secondary_color' => '#14532d',
            ],
        ])->assertOk();

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('data.primary_color', '#22c55e')
            ->assertJsonPath('data.secondary_color', '#14532d');
    }

    public function test_public_settings_expose_site_font_and_new_themes(): void
    {
        Setting::set('homepage_layout', 'blossom', 'homepage');
        Setting::set('site_font', 'vazirmatn', 'homepage');

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('data.homepage_layout', 'blossom')
            ->assertJsonPath('data.site_font', 'vazirmatn');
    }

    public function test_public_settings_expose_site_font_size(): void
    {
        Setting::set('site_font_size', '18', 'homepage');

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('data.site_font_size', 18);
    }
}
