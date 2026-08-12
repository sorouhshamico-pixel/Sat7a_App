<?php

namespace Tests\Feature\Api\V1\Dispatch;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Dispatch\Models\DispatchOffer;
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

class DispatchFlowTest extends TestCase
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
     * @return array{0: Provider, 1: string, 2: string, 3: string} provider, driver token, driver public id, tow truck public id
     */
    private function registerApprovedProviderWithAvailableTruck(
        string $ownerPhone,
        string $driverPhone,
        float $latitude = self::PICKUP_LAT,
        float $longitude = self::PICKUP_LNG,
    ): array {
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
            'current_latitude' => $latitude,
            'current_longitude' => $longitude,
        ]);

        $driverUser = $provider->drivers()->where('public_id', $driverId)->firstOrFail()->user;
        $driverToken = $this->tokenFor($driverUser);

        return [$provider->fresh(), $driverToken, $driverId, $truckId];
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

    private function createOrder(string $customerToken): string
    {
        $vehicleId = $this->actingAsToken('POST', $customerToken, '/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $response = $this->actingAsToken('POST', $customerToken, '/api/v1/customers/me/orders', [
            'vehicle_id' => $vehicleId,
            'service_type' => ServiceCapability::StandardFlatbed->value,
            'vehicle_category' => VehicleCategory::Sedan->value,
            'pickup_latitude' => self::PICKUP_LAT,
            'pickup_longitude' => self::PICKUP_LNG,
            'pickup_formatted_address' => 'King Fahd Road, Riyadh',
            'dropoff_latitude' => 24.6408,
            'dropoff_longitude' => 46.7728,
            'dropoff_formatted_address' => 'Al Malaz, Riyadh',
        ]);

        $response->assertCreated();

        return $response->json('data.order.id');
    }

    public function test_order_creation_automatically_starts_dispatch_and_offers_the_nearest_eligible_truck(): void
    {
        $this->seedActiveVersion();
        [, $driverToken] = $this->registerApprovedProviderWithAvailableTruck('+966501110001', '+966502220001');

        $customer = $this->authenticateNewCustomer('+966503330001');
        $orderId = $this->createOrder($this->tokenFor($customer));

        $this->assertDatabaseHas('orders', ['public_id' => $orderId, 'status' => 'searching_provider', 'current_dispatch_wave' => 1]);
        $this->assertDatabaseHas('dispatch_offers', ['status' => 'pending', 'wave' => 1]);

        $offers = $this->actingAsToken('GET', $driverToken, '/api/v1/drivers/me/dispatch-offers');
        $offers->assertOk();
        $this->assertCount(1, $offers->json('data.offers'));
        $offers->assertJsonPath('data.offers.0.order.id', $orderId);
    }

    public function test_a_driver_can_accept_an_offer_which_assigns_the_order_and_reserves_the_truck(): void
    {
        $this->seedActiveVersion();
        [, $driverToken, $driverId, $truckId] = $this->registerApprovedProviderWithAvailableTruck('+966501110002', '+966502220002');

        $customer = $this->authenticateNewCustomer('+966503330002');
        $orderId = $this->createOrder($this->tokenFor($customer));

        $offerId = $this->actingAsToken('GET', $driverToken, '/api/v1/drivers/me/dispatch-offers')
            ->json('data.offers.0.id');

        $response = $this->actingAsToken('POST', $driverToken, "/api/v1/drivers/me/dispatch-offers/{$offerId}/accept");

        $response->assertOk();
        $response->assertJsonPath('data.order.status', 'provider_assigned');
        $response->assertJsonPath('data.order.assigned_driver.id', $driverId);
        $response->assertJsonPath('data.order.assigned_tow_truck.id', $truckId);

        $this->assertDatabaseHas('orders', ['public_id' => $orderId, 'status' => 'provider_assigned']);
        $this->assertDatabaseHas('tow_trucks', ['public_id' => $truckId, 'status' => 'reserved']);
        $this->assertDatabaseHas('dispatch_offers', ['public_id' => $offerId, 'status' => 'accepted']);
        $this->assertDatabaseHas('order_status_history', ['to_status' => 'provider_assigned']);
    }

    public function test_accepting_an_offer_supersedes_the_other_pending_offers_for_the_same_order(): void
    {
        $this->seedActiveVersion();
        [, $driverAToken] = $this->registerApprovedProviderWithAvailableTruck('+966501110003', '+966502220003', 24.7140, 46.6753);
        [, $driverBToken] = $this->registerApprovedProviderWithAvailableTruck('+966501110004', '+966502220004', 24.7200, 46.6753);

        $customer = $this->authenticateNewCustomer('+966503330003');
        $this->createOrder($this->tokenFor($customer));

        $offerAId = $this->actingAsToken('GET', $driverAToken, '/api/v1/drivers/me/dispatch-offers')->json('data.offers.0.id');
        $offerBId = $this->actingAsToken('GET', $driverBToken, '/api/v1/drivers/me/dispatch-offers')->json('data.offers.0.id');

        $this->actingAsToken('POST', $driverAToken, "/api/v1/drivers/me/dispatch-offers/{$offerAId}/accept")->assertOk();

        $this->assertDatabaseHas('dispatch_offers', ['public_id' => $offerAId, 'status' => 'accepted']);
        $this->assertDatabaseHas('dispatch_offers', ['public_id' => $offerBId, 'status' => 'superseded']);

        $driverBOffers = $this->actingAsToken('GET', $driverBToken, '/api/v1/drivers/me/dispatch-offers');
        $this->assertCount(0, $driverBOffers->json('data.offers'));
    }

    public function test_a_second_accept_attempt_on_an_already_resolved_offer_is_rejected(): void
    {
        $this->seedActiveVersion();
        [, $driverToken] = $this->registerApprovedProviderWithAvailableTruck('+966501110005', '+966502220005');

        $customer = $this->authenticateNewCustomer('+966503330005');
        $this->createOrder($this->tokenFor($customer));

        $offerId = $this->actingAsToken('GET', $driverToken, '/api/v1/drivers/me/dispatch-offers')->json('data.offers.0.id');

        $first = $this->actingAsToken('POST', $driverToken, "/api/v1/drivers/me/dispatch-offers/{$offerId}/accept");
        $first->assertOk();

        // Simulates a second, racing acceptance attempt for the same offer
        // arriving just after the first has already committed — the
        // guard must reject it, not double-assign the order.
        $second = $this->actingAsToken('POST', $driverToken, "/api/v1/drivers/me/dispatch-offers/{$offerId}/accept");
        $second->assertStatus(409);
        $second->assertJsonPath('errors.0.code', 'DISPATCH_OFFER_NO_LONGER_AVAILABLE');
    }

    public function test_rejecting_the_last_pending_offer_in_a_wave_immediately_escalates(): void
    {
        $this->seedActiveVersion();
        // Wave 1 radius is 5000m — only the near truck is inside it. The
        // far truck is inside wave 2's 15000m radius but outside wave 1's.
        [, $nearDriverToken] = $this->registerApprovedProviderWithAvailableTruck('+966501110006', '+966502220006', 24.7140, 46.6753);
        $this->registerApprovedProviderWithAvailableTruck('+966501110007', '+966502220007', 24.83, 46.6753);

        $customer = $this->authenticateNewCustomer('+966503330006');
        $orderId = $this->createOrder($this->tokenFor($customer));

        $this->assertDatabaseHas('orders', ['public_id' => $orderId, 'current_dispatch_wave' => 1]);

        $offerId = $this->actingAsToken('GET', $nearDriverToken, '/api/v1/drivers/me/dispatch-offers')->json('data.offers.0.id');

        $this->actingAsToken('POST', $nearDriverToken, "/api/v1/drivers/me/dispatch-offers/{$offerId}/reject")->assertOk();

        $this->assertDatabaseHas('orders', ['public_id' => $orderId, 'current_dispatch_wave' => 2]);
        $this->assertDatabaseHas('dispatch_offers', ['status' => 'rejected']);
        $this->assertDatabaseHas('dispatch_offers', ['status' => 'pending', 'wave' => 2]);
    }

    public function test_dispatch_flags_manual_dispatch_required_when_no_candidates_exist_anywhere(): void
    {
        $this->seedActiveVersion();

        $customer = $this->authenticateNewCustomer('+966503330007');
        $orderId = $this->createOrder($this->tokenFor($customer));

        $this->assertDatabaseHas('orders', [
            'public_id' => $orderId,
            'status' => 'searching_provider',
            'manual_dispatch_required' => true,
        ]);
        $this->assertSame(0, DispatchOffer::query()->count());
    }

    public function test_a_driver_cannot_accept_another_drivers_offer(): void
    {
        $this->seedActiveVersion();
        [, $driverAToken] = $this->registerApprovedProviderWithAvailableTruck('+966501110008', '+966502220008');
        [, $driverBToken] = $this->registerApprovedProviderWithAvailableTruck('+966501110009', '+966502220009', 24.9, 46.6753);

        $customer = $this->authenticateNewCustomer('+966503330008');
        $this->createOrder($this->tokenFor($customer));

        $offerAId = $this->actingAsToken('GET', $driverAToken, '/api/v1/drivers/me/dispatch-offers')->json('data.offers.0.id');

        $response = $this->actingAsToken('POST', $driverBToken, "/api/v1/drivers/me/dispatch-offers/{$offerAId}/accept");

        $response->assertStatus(404);
    }

    public function test_an_unauthenticated_request_cannot_reach_driver_dispatch_endpoints(): void
    {
        $this->getJson('/api/v1/drivers/me/dispatch-offers')->assertStatus(401);
    }
}
