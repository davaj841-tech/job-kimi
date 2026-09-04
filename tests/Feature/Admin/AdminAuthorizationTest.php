<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobseeker_cannot_access_admin_api(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'jobseeker', 'status' => 'active']));

        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->getJson('/api/v1/admin/exams')->assertForbidden();
        $this->getJson('/api/v1/admin/dashboard-stats')->assertForbidden();
    }

    public function test_super_admin_can_access_sensitive_routes(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin', 'status' => 'active']));

        $this->getJson('/api/v1/admin/settings')->assertOk();
        $this->getJson('/api/v1/admin/backups')->assertOk();
        $this->getJson('/api/v1/admin/audit-logs')->assertOk();
        $this->getJson('/api/v1/admin/performance')->assertOk();
    }

    public function test_regular_admin_cannot_access_sensitive_routes(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $this->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->getJson('/api/v1/admin/backups')->assertForbidden();
        $this->getJson('/api/v1/admin/audit-logs')->assertForbidden();
        $this->getJson('/api/v1/admin/performance')->assertForbidden();
    }

    public function test_regular_admin_can_access_non_sensitive_resources(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $this->getJson('/api/v1/admin/users')->assertOk();
        $this->getJson('/api/v1/admin/exams')->assertOk();
        $this->getJson('/api/v1/admin/dashboard-stats')->assertOk();
        $this->getJson('/api/v1/admin/transactions')->assertOk();
        $this->getJson('/api/v1/admin/wallets')->assertOk();
    }

    public function test_operator_without_permission_is_blocked(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['exams'],
        ]));

        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->getJson('/api/v1/admin/exams')->assertOk();
        $this->getJson('/api/v1/admin/wallets')->assertForbidden();
    }

    public function test_operator_with_wallets_permission_can_access_wallets(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['wallets'],
        ]));

        $this->getJson('/api/v1/admin/wallets')->assertOk();
        $this->getJson('/api/v1/admin/wallets/stats')->assertOk();
    }

    public function test_regular_admin_cannot_promote_user_to_super_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $target = User::factory()->create([
            'role' => 'jobseeker',
            'status' => 'active',
            'mobile' => '09120000003',
        ]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/users/'.$target->id, [
            'name' => $target->name,
            'mobile' => '09120000003',
            'role' => 'super_admin',
        ])->assertForbidden();
    }

    public function test_super_admin_can_promote_user_to_admin(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $target = User::factory()->create([
            'role' => 'jobseeker',
            'status' => 'active',
            'mobile' => '09120000004',
        ]);
        Sanctum::actingAs($super);

        $this->putJson('/api/v1/admin/users/'.$target->id, [
            'name' => $target->name,
            'mobile' => '09120000004',
            'role' => 'admin',
        ])->assertOk();

        $this->assertSame('admin', $target->fresh()->role);
    }

    public function test_regular_admin_cannot_modify_protected_staff_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $otherAdmin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'mobile' => '09120000005',
        ]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/users/'.$otherAdmin->id, [
            'name' => 'Changed',
            'mobile' => '09120000005',
        ])->assertForbidden();
    }

    public function test_operator_sees_masked_national_code(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['users'],
        ]);
        $user = User::factory()->create([
            'national_code' => '1234567890',
            'mobile' => '09121111111',
        ]);
        Sanctum::actingAs($operator);

        $response = $this->getJson('/api/v1/admin/users/'.$user->id)->assertOk();

        $this->assertSame('***7890', $response->json('data.national_code'));
    }

    public function test_wallet_admin_charge_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $user = User::factory()->create(['wallet_balance' => 0]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/wallets/'.$user->id.'/charge', [
            'amount' => 5000,
            'description' => 'تست شارژ',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'wallet.admin_charged',
            'user_id' => $admin->id,
        ]);
    }

    public function test_wallet_admin_deduct_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $user = User::factory()->create(['wallet_balance' => 10000]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/wallets/'.$user->id.'/deduct', [
            'amount' => 3000,
            'reason' => 'تست کسر',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'wallet.admin_deducted',
            'user_id' => $admin->id,
        ]);
    }

    public function test_only_super_admin_can_purge_audit_logs(): void
    {
        AuditLog::query()->create([
            'user_id' => null,
            'action' => 'test.action',
            'created_at' => now()->subDay(),
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Sanctum::actingAs($admin);
        $this->deleteJson('/api/v1/admin/audit-logs', [
            'date_from' => now()->subDays(2)->toDateString(),
            'date_to' => now()->toDateString(),
        ])->assertForbidden();

        $super = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($super);
        $this->deleteJson('/api/v1/admin/audit-logs', [
            'date_from' => now()->subDays(2)->toDateString(),
            'date_to' => now()->toDateString(),
        ])->assertOk();
    }

    public function test_filament_sensitive_resources_are_super_admin_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $super = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin);
        $this->assertFalse(SettingResource::canViewAny());
        $this->assertFalse(UserResource::canViewAny());

        $this->actingAs($super);
        $this->assertTrue(SettingResource::canViewAny());
        $this->assertTrue(UserResource::canViewAny());
    }

    public function test_exam_subjects_and_crawler_require_permissions(): void
    {
        Exam::factory()->create();

        Sanctum::actingAs(User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['exams'],
        ]));
        $this->getJson('/api/v1/admin/exam-subjects')->assertOk();
        $this->getJson('/api/v1/admin/crawler-runs')->assertForbidden();

        Sanctum::actingAs(User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['aggregation'],
        ]));
        $this->getJson('/api/v1/admin/crawler-runs')->assertOk();
    }
}
