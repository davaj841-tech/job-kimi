<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\JobClassification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class JobClassificationAdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_tree_and_flat_lists(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        JobClassification::query()->create([
            'name' => 'طبقه‌بندی تست ایندکس-'.uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->getJson('/api/v1/admin/job-classifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'tree',
                    'flat',
                    'parents',
                ],
            ]);
    }

    public function test_show_route_is_not_registered(): void
    {
        $this->assertFalse(
            Route::has('job-classifications.show'),
            'job-classifications.show must not be registered when show() is not implemented.'
        );
    }

    public function test_get_single_classification_is_not_allowed(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $item = JobClassification::query()->create([
            'name' => 'طبقه‌بندی تست شو-'.uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // show is excluded; URI still accepts PUT/PATCH/DELETE so GET yields 405.
        $this->getJson('/api/v1/admin/job-classifications/'.$item->id)
            ->assertMethodNotAllowed();
    }
}
