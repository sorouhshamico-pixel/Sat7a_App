<?php

namespace Tests\Feature\Api\V1\Tracking;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Orders\Models\Order;
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

class TripProgressTest extends TestCase
{
    use RefreshDatabase;

    private const PICKUP_LAT = 24.7136;

    private const PICKUP_LNG = 46.6753;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
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
     * @return array{0: string, 1: string} driver token, tow truck public id
     */
    private function registerApprovedProviderWithAvailableTruck(string $ownerPhone, string $driverPhone): array
    {
        $this->postJson('/api/v1/providers/register', [
            'business_name' => 'شركة النقل السريع '.$ownerPhone,
            'owner_name' => 'محمد أحمد',
            'contact_phone' => $ownerPhone,
        ])->assertCreated();

        $provider = Provider::query()->where('contact_phone', $ownerPhone)->firstOrFail();
        $provider->status = ProviderStatus::Approved;
        $provider->approved_at = now();
        $provider->save();

        $ownerToken = $this->tokenFor($provider->owner);

        $driverId = $this->actingAsToken('POST', $ownerToken, '/api/v1/providers/me/drivers', [
            'name' => 'سالم القحطاني',
            'phone' => $driverPhone,
        ])->json('data.driver.id');

        $this->actingAsToken('PATCH', $ownerToken, "/api/v1/providers/me/drivers/{$driverId}/availability", [
            'is_available' => true,
        ])->assertOk();

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

        $this->actingAsToken('PATCH', $ownerToken, "/api/v1/providers/me/fleet/{$truckId}/status", [
            'status' => 'available',
        ])->assertOk();

        $provider->towTrucks()->where('public_id', $truckId)->firstOrFail()->update([
            'current_latitude' => self::PICKUP_LAT,
            'current_longitude' => self::PICKUP_LNG,
        ]);

        $driverUser = $provider->drivers()->where('public_id', $driverId)->firstOrFail()->user;
        $driverToken = $this->tokenFor($driverUser);

        return [$driverToken, $truckId];
    }

    private function authenticateNewCustomer(string $phone): User
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

        return User::query()->where('phone', $phone)->firstOrFail();
    }

    /**
     * @return array{0: string, 1: string} order public id, accepted offer public id
     */
    private function createAndAssignOrder(string $customerPhone, string $driverToken): array
    {
        $customer = $this->authenticateNewCustomer($customerPhone);
        $customerToken = $this->tokenFor($customer);

        $vehicleId = $this->actingAsToken('POST', $customerToken, '/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $orderId = $this->actingAsToken('POST', $customerToken, '/api/v1/customers/me/orders', [
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

        $offerId = $this->actingAsToken('GET', $driverToken, '/api/v1/drivers/me/dispatch-offers')
            ->json('data.offers.0.id');

        $this->actingAsToken('POST', $driverToken, "/api/v1/drivers/me/dispatch-offers/{$offerId}/accept")->assertOk();

        return [$orderId, $offerId];
    }

    public function test_a_driver_can_advance_a_trip_through_the_full_chain_and_the_truck_follows_along(): void
    {
        $this->seedActiveVersion();
        [$driverToken, $truckId] = $this->registerApprovedProviderWithAvailableTruck('+966501110060', '+966502220060');
        [$orderId] = $this->createAndAssignOrder('+966503330060', $driverToken);

        $this->assertDatabaseHas('tow_trucks', ['public_id' => $truckId, 'status' => 'reserved']);

        $steps = [
            'provider_en_route' => 'en_route',
            'provider_arrived' => 'arrived',
            'vehicle_loading' => 'loading',
            'trip_started' => 'on_trip',
            'in_transit' => 'on_trip',
            'vehicle_delivered' => 'on_trip',
            'completed' => 'available',
        ];

        foreach ($steps as $orderStatus => $truckStatus) {
            $response = $this->actingAsToken('POST', $driverToken, "/api/v1/drivers/me/orders/{$orderId}/status", [
                'status' => $orderStatus,
            ]);

            $response->assertOk();
            $response->assertJsonPath('data.order.status', $orderStatus);

            $this->assertDatabaseHas('orders', ['public_id' => $orderId, 'status' => $orderStatus]);
            $this->assertDatabaseHas('tow_trucks', ['public_id' => $truckId, 'status' => $truckStatus]);
        }

        $this->assertDatabaseHas('orders', ['public_id' => $orderId, 'status' => 'completed']);
        $order = Order::query()->where('public_id', $orderId)->firstOrFail();
        $this->assertNotNull($order->arrived_at);
        $this->assertNotNull($order->trip_started_at);
        $this->assertNotNull($order->completed_at);
    }

    public function test_a_driver_cannot_skip_a_step(): void
    {
        $this->seedActiveVersion();
        [$driverToken] = $this->registerApprovedProviderWithAvailableTruck('+966501110061', '+966502220061');
        [$orderId] = $this->createAndAssignOrder('+966503330061', $driverToken);

        $response = $this->actingAsToken('POST', $driverToken, "/api/v1/drivers/me/orders/{$orderId}/status", [
            'status' => 'trip_started',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'ORDER_INVALID_TRANSITION');
    }

    public function test_a_driver_cannot_reach_a_cancellation_status_through_this_endpoint(): void
    {
        $this->seedActiveVersion();
        [$driverToken] = $this->registerApprovedProviderWithAvailableTruck('+966501110062', '+966502220062');
        [$orderId] = $this->createAndAssignOrder('+966503330062', $driverToken);

        $response = $this->actingAsToken('POST', $driverToken, "/api/v1/drivers/me/orders/{$orderId}/status", [
            'status' => 'cancelled_by_customer',
        ]);

        $response->assertStatus(422);
    }

    public function test_a_driver_cannot_advance_another_drivers_order(): void
    {
        $this->seedActiveVersion();
        [$driverAToken] = $this->registerApprovedProviderWithAvailableTruck('+966501110063', '+966502220063');
        [$orderId] = $this->createAndAssignOrder('+966503330063', $driverAToken);

        [$driverBToken] = $this->registerApprovedProviderWithAvailableTruck('+966501110064', '+966502220064');

        $response = $this->actingAsToken('POST', $driverBToken, "/api/v1/drivers/me/orders/{$orderId}/status", [
            'status' => 'provider_en_route',
        ]);

        $response->assertStatus(404);
    }

    public function test_cancelling_an_order_after_acceptance_frees_the_reserved_truck(): void
    {
        $this->seedActiveVersion();
        [$driverToken, $truckId] = $this->registerApprovedProviderWithAvailableTruck('+966501110065', '+966502220065');
        [$orderId] = $this->createAndAssignOrder('+966503330065', $driverToken);

        $this->assertDatabaseHas('tow_trucks', ['public_id' => $truckId, 'status' => 'reserved']);

        $customer = User::query()->where('phone', '+966503330065')->firstOrFail();
        $customerToken = $this->tokenFor($customer);

        $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/cancel")->assertOk();

        $this->assertDatabaseHas('tow_trucks', ['public_id' => $truckId, 'status' => 'available']);
    }
}
