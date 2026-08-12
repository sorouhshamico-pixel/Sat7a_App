<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Pricing\Enums\VehicleCategory;
use App\Domain\Pricing\Models\PricingRuleVersion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function staffWithRole(RoleName $role): User
    {
        $user = User::factory()->admin()->create();
        $user->roles()->attach(Role::where('name', $role->value)->firstOrFail());

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function validVersionPayload(): array
    {
        return [
            'version_label' => 'v1-'.uniqid(),
            'base_fee' => 5000,
            'minimum_fare' => 8000,
            'distance_rate_per_km' => 200,
            'service_type_fees' => [ServiceCapability::StandardFlatbed->value => 1000],
            'vehicle_category_multipliers' => [VehicleCategory::Sedan->value => 1.0],
            'platform_service_fee_percentage' => 0.10,
            'vat_percentage' => 0.15,
        ];
    }

    public function test_dispatcher_cannot_view_pricing_versions(): void
    {
        $dispatcher = $this->staffWithRole(RoleName::Dispatcher);
        $token = $this->tokenFor($dispatcher);

        $this->withToken($token)->getJson('/api/v1/admin/pricing/versions')->assertStatus(403);
    }

    public function test_finance_officer_can_view_but_not_update_pricing(): void
    {
        $financeOfficer = $this->staffWithRole(RoleName::FinanceOfficer);
        $token = $this->tokenFor($financeOfficer);

        $this->withToken($token)->getJson('/api/v1/admin/pricing/versions')->assertOk();
        $this->withToken($token)->postJson('/api/v1/admin/pricing/versions', $this->validVersionPayload())
            ->assertStatus(403);
    }

    public function test_super_admin_can_create_a_version_and_it_is_audited_but_not_active(): void
    {
        $superAdmin = $this->staffWithRole(RoleName::SuperAdmin);
        $token = $this->tokenFor($superAdmin);

        $response = $this->withToken($token)->postJson('/api/v1/admin/pricing/versions', $this->validVersionPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.version.is_active', false);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pricing.version_created',
            'entity_type' => 'pricing_rule_version',
        ]);
    }

    public function test_creating_a_version_rejects_invalid_service_type_keys(): void
    {
        $superAdmin = $this->staffWithRole(RoleName::SuperAdmin);
        $token = $this->tokenFor($superAdmin);

        $payload = $this->validVersionPayload();
        $payload['service_type_fees'] = ['not_a_real_service_type' => 500];

        $this->withToken($token)->postJson('/api/v1/admin/pricing/versions', $payload)->assertStatus(422);
    }

    public function test_activating_a_version_deactivates_the_previous_one_and_is_audited(): void
    {
        $superAdmin = $this->staffWithRole(RoleName::SuperAdmin);

        $versionA = new PricingRuleVersion(array_merge($this->validVersionPayload(), [
            'is_active' => true,
        ]));
        $versionA->created_by = $superAdmin->id;
        $versionA->save();

        $token = $this->tokenFor($superAdmin);
        $versionBId = $this->withToken($token)
            ->postJson('/api/v1/admin/pricing/versions', $this->validVersionPayload())
            ->json('data.version.id');

        $response = $this->withToken($token)->postJson("/api/v1/admin/pricing/versions/{$versionBId}/activate");

        $response->assertOk();
        $response->assertJsonPath('data.version.is_active', true);

        $this->assertFalse($versionA->fresh()->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pricing.version_activated',
            'entity_type' => 'pricing_rule_version',
        ]);
    }
}
