<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Pricing\Enums\VehicleCategory;
use App\Domain\Pricing\Models\PricingRuleVersion;
use App\Domain\Providers\Enums\ProviderStatus;
use App\Domain\Providers\Models\Provider;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DispatchManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PICKUP_LAT = 24.7136;

    private const PICKUP_LNG = 46.6753;

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

    private function seedActiveVersion(): void
    {
        $admin = User::factory()->admin()->create();

        $version = new PricingRuleVersion([
            'version_label' => 'v1',
            'base_fee' => 5000,
            'minimum_fare' => 8000,
            'distance_rate_per_km' => 200,
            'service_type_fees' => [ServiceCapability::StandardFlatbed->value => 1000],
            'vehicle_category_multipliers' => [VehicleCategory::Sedan->value => 1.0],
            'platform_service_fee_percentage' => 0.10,
            'vat_percentage' => 0.15,
            'is_active' => true,
        ]);
        $version->created_by = $admin->id;
        $version->save();
    }

    /**
     * @return array{0: string, 1: bool} tow truck public id, whether it's dispatch-eligible
     */
    private function registerProviderWithTruck(string $ownerPhone, string $driverPhone, bool $approveAndActivate): array
    {
        $this->postJson('/api/v1/providers/register', [
            'business_name' => 'شركة النقل السريع '.$ownerPhone,
            'owner_name' => 'محمد أحمد',
            'contact_phone' => $ownerPhone,
        ])->assertCreated();

        $provider = Provider::query()->where('contact_phone', $ownerPhone)->firstOrFail();

        if ($approveAndActivate) {
            $provider->status = ProviderStatus::Approved;
            $provider->approved_at = now();
            $provider->save();
        }

        $ownerToken = $this->tokenFor($provider->owner);

        $driverId = $this->actingAsToken('POST', $ownerToken, '/api/v1/providers/me/drivers', [
            'name' => 'سالم القحطاني',
            'phone' => $driverPhone,
        ])->json('data.driver.id');

        $truckId = $this->actingAsToken('POST', $ownerToken, '/api/v1/providers/me/fleet', [
            'manufacturer' => 'Isuzu',
            'model' => 'NPR',
            'year' => 2022,
            'plate_number' => 'PLT-'.$driverPhone,
            'service_capabilities' => [ServiceCapability::StandardFlatbed->value],
        ])->json('data.tow_truck.id');

        $this->actingAsToken('PATCH', $ownerToken, "/api/v1/providers/me/fleet/{$truckId}/driver", [
            'driver_id' => $driverId,
        ])->assertOk();

        if ($approveAndActivate) {
            $this->actingAsToken('PATCH', $ownerToken, "/api/v1/providers/me/drivers/{$driverId}/availability", [
                'is_available' => true,
            ])->assertOk();

            $this->actingAsToken('PATCH', $ownerToken, "/api/v1/providers/me/fleet/{$truckId}/status", [
                'status' => 'available',
            ])->assertOk();

            $provider->towTrucks()->where('public_id', $truckId)->firstOrFail()->update([
                'current_latitude' => self::PICKUP_LAT,
                'current_longitude' => self::PICKUP_LNG,
            ]);
        }

        return [$truckId, $approveAndActivate];
    }

    private function createOrderAsNewCustomer(string $phone): string
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => $phone,
            'user_type' => UserType::Customer->value,
        ])->assertOk();

        $otp = OtpCode::query()->where('phone', $phone)->firstOrFail();
        $otp->update(['code_hash' => Hash::make('123456')]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $phone,
            'code' => '123456',
            'user_type' => UserType::Customer->value,
        ])->assertOk();

        $user = User::query()->where('phone', $phone)->firstOrFail();
        $token = $this->tokenFor($user);

        $vehicleId = $this->actingAsToken('POST', $token, '/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        return $this->actingAsToken('POST', $token, '/api/v1/customers/me/orders', [
            'vehicle_id' => $vehicleId,
            'service_type' => ServiceCapability::StandardFlatbed->value,
            'vehicle_category' => VehicleCategory::Sedan->value,
            'pickup_latitude' => self::PICKUP_LAT,
            'pickup_longitude' => self::PICKUP_LNG,
            'pickup_formatted_address' => 'King Fahd Road, Riyadh',
            'dropoff_latitude' => 24.6408,
            'dropoff_longitude' => 46.7728,
            'dropoff_formatted_address' => 'Al Malaz, Riyadh',
        ])->json('data.order.id');
    }

    public function test_customer_support_can_view_offers_but_not_assign_or_retry(): void
    {
        $this->seedActiveVersion();
        $orderId = $this->createOrderAsNewCustomer('+966503330010');

        $supportAgent = $this->staffWithRole(RoleName::CustomerSupport);
        $token = $this->tokenFor($supportAgent);

        $this->actingAsToken('GET', $token, "/api/v1/admin/orders/{$orderId}/dispatch-offers")->assertOk();
        $this->actingAsToken('POST', $token, "/api/v1/admin/orders/{$orderId}/dispatch/retry")->assertStatus(403);
        $this->actingAsToken('POST', $token, "/api/v1/admin/orders/{$orderId}/dispatch/assign", ['tow_truck_id' => 'irrelevant'])
            ->assertStatus(403);
    }

    public function test_operations_manager_can_manually_assign_an_eligible_truck(): void
    {
        $this->seedActiveVersion();
        $orderId = $this->createOrderAsNewCustomer('+966503330011');

        // No eligible trucks exist, so automated dispatch will have already
        // flagged manual_dispatch_required — this is exactly the scenario
        // manual assignment exists for.
        $this->assertDatabaseHas('orders', ['public_id' => $orderId, 'manual_dispatch_required' => true]);

        [$truckId] = $this->registerProviderWithTruck('+966501110020', '+966502220020', approveAndActivate: true);

        $operationsManager = $this->staffWithRole(RoleName::OperationsManager);
        $token = $this->tokenFor($operationsManager);

        $response = $this->actingAsToken('POST', $token, "/api/v1/admin/orders/{$orderId}/dispatch/assign", [
            'tow_truck_id' => $truckId,
            'reason' => 'Automated search found nothing; manually placed.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.order.status', 'provider_assigned');
        $response->assertJsonPath('data.order.assigned_tow_truck.id', $truckId);
        $this->assertDatabaseHas('tow_trucks', ['public_id' => $truckId, 'status' => 'reserved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'orders.manually_assigned', 'entity_type' => 'order']);
    }

    public function test_manually_assigning_an_ineligible_truck_is_rejected(): void
    {
        $this->seedActiveVersion();
        $orderId = $this->createOrderAsNewCustomer('+966503330012');

        // Provider not approved, truck left offline — not dispatch-eligible.
        [$truckId] = $this->registerProviderWithTruck('+966501110021', '+966502220021', approveAndActivate: false);

        $operationsManager = $this->staffWithRole(RoleName::OperationsManager);
        $token = $this->tokenFor($operationsManager);

        $response = $this->actingAsToken('POST', $token, "/api/v1/admin/orders/{$orderId}/dispatch/assign", [
            'tow_truck_id' => $truckId,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'TOW_TRUCK_NOT_ELIGIBLE');
    }

    public function test_dispatcher_can_retry_dispatch_after_a_truck_comes_online(): void
    {
        $this->seedActiveVersion();
        $orderId = $this->createOrderAsNewCustomer('+966503330013');

        $this->assertDatabaseHas('orders', ['public_id' => $orderId, 'manual_dispatch_required' => true]);

        $this->registerProviderWithTruck('+966501110022', '+966502220022', approveAndActivate: true);

        $dispatcher = $this->staffWithRole(RoleName::Dispatcher);
        $token = $this->tokenFor($dispatcher);

        $response = $this->actingAsToken('POST', $token, "/api/v1/admin/orders/{$orderId}/dispatch/retry");

        $response->assertOk();
        $this->assertDatabaseHas('orders', ['public_id' => $orderId, 'manual_dispatch_required' => false]);
        $this->assertDatabaseHas('dispatch_offers', ['status' => 'pending', 'wave' => 1]);
    }
}
