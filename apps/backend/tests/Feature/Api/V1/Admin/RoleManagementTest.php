<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function fullTokenFor(User $user): string
    {
        return $user->createToken('test', ['*'])->plainTextToken;
    }

    public function test_unauthenticated_request_cannot_list_roles(): void
    {
        $this->getJson('/api/v1/admin/roles')->assertStatus(401);
    }

    public function test_super_admin_can_list_the_full_role_catalog(): void
    {
        $superAdmin = User::factory()->admin()->create();
        $superAdmin->roles()->attach(
            Role::where('name', RoleName::SuperAdmin->value)->firstOrFail(),
        );

        $response = $this->withToken($this->fullTokenFor($superAdmin))->getJson('/api/v1/admin/roles');

        $response->assertOk();
        $this->assertCount(count(RoleName::cases()), $response->json('data.roles'));
    }

    public function test_admin_staff_without_roles_manage_permission_is_forbidden(): void
    {
        $dispatcher = User::factory()->admin()->create();
        $dispatcher->roles()->attach(
            Role::where('name', RoleName::Dispatcher->value)->firstOrFail(),
        );

        $response = $this->withToken($this->fullTokenFor($dispatcher))->getJson('/api/v1/admin/roles');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_assign_a_role_to_a_user_and_it_is_audited(): void
    {
        $superAdmin = User::factory()->admin()->create();
        $superAdmin->roles()->attach(
            Role::where('name', RoleName::SuperAdmin->value)->firstOrFail(),
        );

        $target = User::factory()->admin()->create();

        $response = $this->withToken($this->fullTokenFor($superAdmin))
            ->postJson("/api/v1/admin/users/{$target->public_id}/roles", ['role' => RoleName::FinanceOfficer->value]);

        $response->assertOk();
        $this->assertTrue($target->fresh()->hasRole(RoleName::FinanceOfficer->value));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role.assigned',
            'entity_type' => 'user',
            'entity_id' => $target->public_id,
            'actor_id' => $superAdmin->id,
        ]);
    }

    public function test_super_admin_can_revoke_a_role_and_it_is_audited(): void
    {
        $superAdmin = User::factory()->admin()->create();
        $superAdmin->roles()->attach(
            Role::where('name', RoleName::SuperAdmin->value)->firstOrFail(),
        );

        $target = User::factory()->admin()->create();
        $target->roles()->attach(
            Role::where('name', RoleName::FinanceOfficer->value)->firstOrFail(),
        );

        $response = $this->withToken($this->fullTokenFor($superAdmin))
            ->deleteJson("/api/v1/admin/users/{$target->public_id}/roles/".RoleName::FinanceOfficer->value);

        $response->assertOk();
        $this->assertFalse($target->fresh()->hasRole(RoleName::FinanceOfficer->value));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role.revoked',
            'entity_type' => 'user',
            'entity_id' => $target->public_id,
        ]);
    }

    public function test_permission_granted_by_role_actually_authorizes_the_matching_gate_ability(): void
    {
        $financeOfficer = User::factory()->admin()->create();
        $financeOfficer->roles()->attach(
            Role::where('name', RoleName::FinanceOfficer->value)->firstOrFail(),
        );

        $this->assertTrue($financeOfficer->can('payments.refund'));
        $this->assertFalse($financeOfficer->can('roles.manage'));
    }

    public function test_audit_log_is_immutable_by_convention_and_never_touched_by_role_read_endpoints(): void
    {
        $superAdmin = User::factory()->admin()->create();
        $superAdmin->roles()->attach(
            Role::where('name', RoleName::SuperAdmin->value)->firstOrFail(),
        );

        $before = AuditLog::count();

        $this->withToken($this->fullTokenFor($superAdmin))->getJson('/api/v1/admin/roles')->assertOk();

        $this->assertSame($before, AuditLog::count());
    }
}
