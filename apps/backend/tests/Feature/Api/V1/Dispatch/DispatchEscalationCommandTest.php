<?php

namespace Tests\Feature\Api\V1\Dispatch;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Pricing\Enums\VehicleCategory;
use App\Domain\Pricing\Models\PricingRuleVersion;
use App\Domain\Providers\Enums\ProviderStatus;
use App\Domain\Providers\Models\Provider;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DispatchEscalationCommandTest extends TestCase
{
    use RefreshDatabase;

    private const PICKUP_LAT = 24.7136;

    private const PICKUP_LNG = 46.6753;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

    private function registerApprovedProviderWithAvailableTruck(
        string $ownerPhone,
        string $driverPhone,
        float $latitude,
        float $longitude,
    ): void {
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

    public function test_an_unanswered_offer_expires_and_escalates_to_the_next_wave(): void
    {
        $this->seedActiveVersion();
        // Only inside wave 1's 5000m radius.
        $this->registerApprovedProviderWithAvailableTruck('+966501110030', '+966502220030', 24.7140, 46.6753);
        // Only inside wave 2's 15000m radius, outside wave 1's.
        $this->registerApprovedProviderWithAvailableTruck('+966501110031', '+966502220031', 24.83, 46.6753);

        $orderId = $this->createOrderAsNewCustomer('+966503330030');

        $this->assertDatabaseHas('orders', ['public_id' => $orderId, 'current_dispatch_wave' => 1]);
        $this->assertDatabaseHas('dispatch_offers', ['status' => 'pending', 'wave' => 1]);

        Carbon::setTestNow(now()->addSeconds((int) config('dispatch.offer_ttl_seconds') + 5));

        $this->artisan('dispatch:escalate-stale-offers')->assertExitCode(0);

        $this->assertDatabaseHas('dispatch_offers', ['status' => 'expired', 'wave' => 1]);
        $this->assertDatabaseHas('orders', ['public_id' => $orderId, 'current_dispatch_wave' => 2]);
        $this->assertDatabaseHas('dispatch_offers', ['status' => 'pending', 'wave' => 2]);
    }

    public function test_an_unanswered_offer_with_no_further_candidates_flags_manual_dispatch(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110032', '+966502220032', 24.7140, 46.6753);

        $orderId = $this->createOrderAsNewCustomer('+966503330031');

        $this->assertDatabaseHas('orders', ['public_id' => $orderId, 'manual_dispatch_required' => false]);

        Carbon::setTestNow(now()->addSeconds((int) config('dispatch.offer_ttl_seconds') + 5));

        $this->artisan('dispatch:escalate-stale-offers')->assertExitCode(0);

        $this->assertDatabaseHas('dispatch_offers', ['status' => 'expired']);
        $this->assertDatabaseHas('orders', ['public_id' => $orderId, 'manual_dispatch_required' => true]);
    }
}
