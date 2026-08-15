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

class LiveLocationTest extends TestCase
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

    private function createAndAssignOrder(string $customerPhone, string $driverToken): string
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

        return $orderId;
    }

    public function test_a_driver_ping_updates_their_tow_trucks_current_position(): void
    {
        [$driverToken, $truckId] = $this->registerApprovedProviderWithAvailableTruck('+966501110070', '+966502220070');

        $response = $this->actingAsToken('POST', $driverToken, '/api/v1/drivers/me/location', [
            'latitude' => 24.8,
            'longitude' => 46.7,
            'heading' => 90,
            'speed_kmh' => 40,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.tracked', false);

        $this->assertDatabaseHas('tow_trucks', [
            'public_id' => $truckId,
            'current_latitude' => 24.8,
            'current_longitude' => 46.7,
        ]);
    }

    public function test_a_ping_while_on_an_active_order_is_recorded_against_the_order(): void
    {
        $this->seedActiveVersion();
        [$driverToken] = $this->registerApprovedProviderWithAvailableTruck('+966501110071', '+966502220071');
        $orderId = $this->createAndAssignOrder('+966503330071', $driverToken);

        $response = $this->actingAsToken('POST', $driverToken, '/api/v1/drivers/me/location', [
            'latitude' => 24.72,
            'longitude' => 46.68,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.tracked', true);

        $this->assertDatabaseCount('order_location_pings', 1);
        $this->assertDatabaseHas('order_location_pings', [
            'latitude' => 24.72,
            'longitude' => 46.68,
        ]);

        $order = Order::query()->where('public_id', $orderId)->firstOrFail();
        $this->assertDatabaseHas('order_location_pings', ['order_id' => $order->id]);
    }

    public function test_a_ping_after_trip_completion_is_no_longer_tracked_against_the_order(): void
    {
        $this->seedActiveVersion();
        [$driverToken] = $this->registerApprovedProviderWithAvailableTruck('+966501110072', '+966502220072');
        $orderId = $this->createAndAssignOrder('+966503330072', $driverToken);

        foreach (['provider_en_route', 'provider_arrived', 'vehicle_loading', 'trip_started', 'in_transit', 'vehicle_delivered', 'completed'] as $status) {
            $this->actingAsToken('POST', $driverToken, "/api/v1/drivers/me/orders/{$orderId}/status", ['status' => $status])
                ->assertOk();
        }

        $this->actingAsToken('POST', $driverToken, '/api/v1/drivers/me/location', [
            'latitude' => 24.9,
            'longitude' => 46.9,
        ])->assertJsonPath('data.tracked', false);
    }

    public function test_location_ping_validates_coordinate_bounds(): void
    {
        [$driverToken] = $this->registerApprovedProviderWithAvailableTruck('+966501110073', '+966502220073');

        $response = $this->actingAsToken('POST', $driverToken, '/api/v1/drivers/me/location', [
            'latitude' => 999,
            'longitude' => 46.7,
        ]);

        $response->assertStatus(422);
    }

    public function test_an_unauthenticated_request_cannot_ping_location(): void
    {
        $this->postJson('/api/v1/drivers/me/location', ['latitude' => 24.7, 'longitude' => 46.7])
            ->assertStatus(401);
    }
}
