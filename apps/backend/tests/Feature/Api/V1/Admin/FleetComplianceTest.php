<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Providers\Models\Provider;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetComplianceTest extends TestCase
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

    private function registerProviderWithDriverAndTruck(): array
    {
        $this->postJson('/api/v1/providers/register', [
            'business_name' => 'شركة النقل السريع',
            'owner_name' => 'محمد أحمد',
            'contact_phone' => '+966501112233',
        ])->assertCreated();
        $provider = Provider::query()->firstOrFail();
        $ownerToken = $this->tokenFor($provider->owner);

        $driverId = $this->withToken($ownerToken)->postJson('/api/v1/providers/me/drivers', [
            'name' => 'سالم القحطاني',
            'phone' => '+966502223344',
        ])->json('data.driver.id');

        $truckId = $this->withToken($ownerToken)->postJson('/api/v1/providers/me/fleet', [
            'manufacturer' => 'Isuzu', 'model' => 'NPR', 'year' => 2022,
            'plate_number' => 'ABC-1234', 'service_capabilities' => [ServiceCapability::StandardFlatbed->value],
        ])->json('data.tow_truck.id');

        return [$provider, $driverId, $truckId];
    }

    public function test_compliance_officer_can_suspend_a_driver_and_it_is_audited(): void
    {
        [, $driverId] = $this->registerProviderWithDriverAndTruck();
        $officer = $this->staffWithRole(RoleName::ComplianceOfficer);
        $token = $this->tokenFor($officer);

        $response = $this->actingAsToken('POST', $token, "/api/v1/admin/drivers/{$driverId}/suspend", [
            'reason' => 'License expired.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.driver.status', 'suspended');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'driver.suspended',
            'entity_type' => 'driver',
            'entity_id' => $driverId,
            'actor_id' => $officer->id,
        ]);
    }

    public function test_compliance_officer_can_suspend_a_tow_truck_and_it_is_audited(): void
    {
        [, , $truckId] = $this->registerProviderWithDriverAndTruck();
        $officer = $this->staffWithRole(RoleName::ComplianceOfficer);
        $token = $this->tokenFor($officer);

        $response = $this->actingAsToken('POST', $token, "/api/v1/admin/tow-trucks/{$truckId}/suspend", [
            'reason' => 'Vehicle registration expired.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.tow_truck.status', 'suspended');

        $this->assertDatabaseHas('audit_logs', ['action' => 'tow_truck.suspended', 'entity_type' => 'tow_truck']);
    }

    public function test_a_provider_owner_cannot_reach_the_admin_suspend_endpoint_for_any_driver(): void
    {
        [$provider, $driverId] = $this->registerProviderWithDriverAndTruck();
        $ownerToken = $this->tokenFor($provider->owner);

        // The owner legitimately has drivers.manage (their own fleet) but
        // must not be able to reach the platform-wide compliance suspend
        // endpoint, which requires the distinct drivers.suspend permission.
        $response = $this->actingAsToken('POST', $ownerToken, "/api/v1/admin/drivers/{$driverId}/suspend", [
            'reason' => 'Trying to suspend my own driver via the admin route.',
        ]);

        $response->assertStatus(403);
    }

    public function test_dispatcher_cannot_suspend_a_driver(): void
    {
        [, $driverId] = $this->registerProviderWithDriverAndTruck();
        $dispatcher = $this->staffWithRole(RoleName::Dispatcher);
        $token = $this->tokenFor($dispatcher);

        $this->actingAsToken('POST', $token, "/api/v1/admin/drivers/{$driverId}/suspend", [
            'reason' => 'Not my job.',
        ])->assertStatus(403);
    }
}
