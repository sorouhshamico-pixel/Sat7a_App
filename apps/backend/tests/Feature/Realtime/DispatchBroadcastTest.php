<?php

namespace Tests\Feature\Realtime;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Dispatch\Events\DispatchOfferCreated;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Pricing\Enums\VehicleCategory;
use App\Domain\Pricing\Models\PricingRuleVersion;
use App\Domain\Providers\Enums\ProviderStatus;
use App\Domain\Providers\Models\Provider;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DispatchBroadcastTest extends TestCase
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

    private function registerApprovedProviderWithAvailableTruck(string $ownerPhone, string $driverPhone): string
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

        return $driverId;
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

    public function test_creating_an_order_broadcasts_a_dispatch_offer_to_the_matched_driver(): void
    {
        Event::fake([DispatchOfferCreated::class]);

        $this->seedActiveVersion();
        $driverId = $this->registerApprovedProviderWithAvailableTruck('+966501110040', '+966502220040');

        $customer = $this->authenticateNewCustomer('+966503330042');
        $token = $this->tokenFor($customer);

        $vehicleId = $this->actingAsToken('POST', $token, '/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $this->actingAsToken('POST', $token, '/api/v1/customers/me/orders', [
            'vehicle_id' => $vehicleId,
            'service_type' => ServiceCapability::StandardFlatbed->value,
            'vehicle_category' => VehicleCategory::Sedan->value,
            'pickup_latitude' => self::PICKUP_LAT,
            'pickup_longitude' => self::PICKUP_LNG,
            'pickup_formatted_address' => 'King Fahd Road, Riyadh',
            'dropoff_latitude' => 24.6408,
            'dropoff_longitude' => 46.7728,
            'dropoff_formatted_address' => 'Al Malaz, Riyadh',
        ])->assertCreated();

        Event::assertDispatched(DispatchOfferCreated::class, function (DispatchOfferCreated $event) use ($driverId) {
            $channels = $event->broadcastOn();

            return $channels[0]->name === "private-drivers.{$driverId}";
        });
    }
}
