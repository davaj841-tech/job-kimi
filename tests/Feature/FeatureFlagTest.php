<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\User;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_features_endpoint_returns_flags(): void
    {
        Feature::query()->create([
            'name' => 'wallet',
            'enabled' => true,
            'description' => 'کیف پول',
        ]);

        $this->getJson('/api/v1/features')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.wallet.enabled', true);
    }

    public function test_middleware_blocks_disabled_feature(): void
    {
        Feature::query()->create([
            'name' => 'wallet',
            'enabled' => false,
            'description' => 'کیف پول',
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/wallet')
            ->assertForbidden()
            ->assertJsonPath('message', 'این قابلیت در حال حاضر غیرفعال است.');
    }

    public function test_cache_invalidates_on_toggle(): void
    {
        $service = app(FeatureFlagService::class);

        Feature::query()->create([
            'name' => 'pdf-store',
            'enabled' => false,
            'description' => 'PDF',
        ]);

        $this->assertFalse($service->isEnabled('pdf-store'));
        $this->assertTrue(Cache::has('features.all'));

        $service->enable('pdf-store');

        $this->assertFalse(Cache::has('features.all'));
        $this->assertTrue($service->isEnabled('pdf-store'));
    }

    public function test_config_lookup(): void
    {
        Feature::query()->create([
            'name' => 'ai-resume',
            'enabled' => true,
            'config' => ['model' => 'gpt'],
            'description' => 'AI',
        ]);

        $service = app(FeatureFlagService::class);

        $this->assertSame('gpt', $service->config('ai-resume', 'model'));
        $this->assertSame(['model' => 'gpt'], $service->config('ai-resume'));
    }
}
