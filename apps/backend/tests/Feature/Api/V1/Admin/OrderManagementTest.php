<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Pricing\Enums\VehicleCategory;
use App\Domain\Pricing\Models\PricingRuleVersion;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderManagementTest extends TestCase
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

    private function createOrderAsNewCustomer(string $phone = '+966501234567'): string
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

        $vehicleId = $this->withToken($token)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $orderId = $this->withToken($token)->postJson('/api/v1/customers/me/orders', [
            'vehicle_id' => $vehicleId,
            'service_type' => ServiceCapability::StandardFlatbed->value,
            'vehicle_category' => VehicleCategory::Sedan->value,
            'pickup_latitude' => 24.7136,
            'pickup_longitude' => 46.6753,
            'pickup_formatted_address' => 'King Fahd Road, Riyadh',
            'dropoff_latitude' => 24.6408,
            'dropoff_longitude' => 46.7728,
            'dropoff_formatted_address' => 'Al Malaz, Riyadh',
        ])->json('data.order.id');

        Auth::forgetGuards();

        return $orderId;
    }

    public function test_a_driver_cannot_list_all_orders(): void
    {
        $this->seedActiveVersion();
        $this->createOrderAsNewCustomer();

        $driver = $this->staffWithRole(RoleName::Driver);
        $token = $this->tokenFor($driver);

        $this->actingAsToken('GET', $token, '/api/v1/admin/orders')->assertStatus(403);
    }

    public function test_dispatcher_can_list_and_view_but_not_cancel_orders(): void
    {
        $this->seedActiveVersion();
        $orderId = $this->createOrderAsNewCustomer();

        $dispatcher = $this->staffWithRole(RoleName::Dispatcher);
        $token = $this->tokenFor($dispatcher);

        $this->actingAsToken('GET', $token, '/api/v1/admin/orders')->assertOk();
        $this->actingAsToken('GET', $token, "/api/v1/admin/orders/{$orderId}")->assertOk();
        $this->actingAsToken('POST', $token, "/api/v1/admin/orders/{$orderId}/cancel", ['reason' => 'test'])
            ->assertStatus(403);
    }

    public function test_operations_manager_can_cancel_an_order_and_it_is_recorded_in_the_timeline(): void
    {
        $this->seedActiveVersion();
        $orderId = $this->createOrderAsNewCustomer();

        $operationsManager = $this->staffWithRole(RoleName::OperationsManager);
        $token = $this->tokenFor($operationsManager);

        $response = $this->actingAsToken('POST', $token, "/api/v1/admin/orders/{$orderId}/cancel", [
            'reason' => 'Customer no longer reachable',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.order.status', 'cancelled_by_admin');
        $response->assertJsonPath('data.order.cancelled_by', 'admin');

        $this->assertDatabaseHas('order_status_history', [
            'to_status' => 'cancelled_by_admin',
        ]);
    }

    public function test_admin_order_cancellation_requires_a_reason(): void
    {
        $this->seedActiveVersion();
        $orderId = $this->createOrderAsNewCustomer();

        $operationsManager = $this->staffWithRole(RoleName::OperationsManager);
        $token = $this->tokenFor($operationsManager);

        $this->actingAsToken('POST', $token, "/api/v1/admin/orders/{$orderId}/cancel", [])
            ->assertStatus(422);
    }
}
