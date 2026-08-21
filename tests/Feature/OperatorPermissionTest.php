<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\OperatorPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperatorPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_without_permission_cannot_access_users_api(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['exams'],
        ]);
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_operator_with_permission_can_access_exams_api(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['exams'],
        ]);
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/admin/exams')->assertOk();
    }

    public function test_operator_cannot_open_settings(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => OperatorPermissions::keys(),
        ]);
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->getJson('/api/v1/admin/analytics/visits')->assertForbidden();
        $this->getJson('/api/v1/admin/backups')->assertForbidden();
    }

    public function test_operator_without_aggregation_cannot_open_crawler_runs(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['exams'],
        ]);
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/admin/crawler-runs')->assertForbidden();
    }

    public function test_regular_admin_cannot_access_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->getJson('/api/v1/admin/backups')->assertForbidden();
    }

    public function test_admin_can_set_operator_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'mobile' => '09120000001',
            'operator_permissions' => ['exams'],
        ]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/users/'.$operator->id, [
            'name' => $operator->name,
            'mobile' => '09120000001',
            'role' => 'operator',
            'operator_permissions' => ['blog', 'tickets'],
        ])->assertOk();

        $this->assertSame(
            ['blog', 'tickets'],
            OperatorPermissions::normalize($operator->fresh()->operator_permissions)
        );
    }

    public function test_operator_cannot_promote_to_admin(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['users'],
        ]);
        $target = User::factory()->create([
            'role' => 'jobseeker',
            'status' => 'active',
            'mobile' => '09120000002',
        ]);
        Sanctum::actingAs($operator);

        $this->putJson('/api/v1/admin/users/'.$target->id, [
            'name' => $target->name,
            'mobile' => '09120000002',
            'role' => 'admin',
        ])->assertForbidden();
    }
}
