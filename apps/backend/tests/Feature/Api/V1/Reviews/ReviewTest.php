<?php

namespace Tests\Feature\Api\V1\Reviews;

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

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private const PICKUP_LAT = 24.7136;

    private const PICKUP_LNG = 46.6753;

    private string $driverToken;

    private string $ownerToken;

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
     * @return string provider public id
     */
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

        $this->ownerToken = $this->tokenFor($provider->owner);

        $driverId = $this->actingAsToken('POST', $this->ownerToken, '/api/v1/providers/me/drivers', [
            'name' => 'سالم القحطاني',
            'phone' => $driverPhone,
        ])->json('data.driver.id');

        $this->actingAsToken('PATCH', $this->ownerToken, "/api/v1/providers/me/drivers/{$driverId}/availability", [
            'is_available' => true,
        ])->assertOk();

        $truckId = $this->actingAsToken('POST', $this->ownerToken, '/api/v1/providers/me/fleet', [
            'manufacturer' => 'Isuzu',
            'model' => 'NPR',
            'year' => 2022,
            'plate_number' => 'PLT-'.$driverPhone,
            'service_capabilities' => [ServiceCapability::StandardFlatbed->value],
        ])->json('data.tow_truck.id');

        $this->actingAsToken('PATCH', $this->ownerToken, "/api/v1/providers/me/fleet/{$truckId}/driver", [
            'driver_id' => $driverId,
        ])->assertOk();

        $this->actingAsToken('PATCH', $this->ownerToken, "/api/v1/providers/me/fleet/{$truckId}/status", [
            'status' => 'available',
        ])->assertOk();

        $provider->towTrucks()->where('public_id', $truckId)->firstOrFail()->update([
            'current_latitude' => self::PICKUP_LAT,
            'current_longitude' => self::PICKUP_LNG,
        ]);

        $driverUser = $provider->drivers()->where('public_id', $driverId)->firstOrFail()->user;
        $this->driverToken = $this->tokenFor($driverUser);

        return $provider->public_id;
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

    private function createOrder(string $customerPhone): string
    {
        $customer = $this->authenticateNewCustomer($customerPhone);
        $customerToken = $this->tokenFor($customer);

        $vehicleId = $this->actingAsToken('POST', $customerToken, '/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        return $this->actingAsToken('POST', $customerToken, '/api/v1/customers/me/orders', [
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

    private function createCompletedOrder(string $customerPhone): string
    {
        $orderId = $this->createOrder($customerPhone);

        $offerId = $this->actingAsToken('GET', $this->driverToken, '/api/v1/drivers/me/dispatch-offers')
            ->json('data.offers.0.id');
        $this->actingAsToken('POST', $this->driverToken, "/api/v1/drivers/me/dispatch-offers/{$offerId}/accept")->assertOk();

        foreach (['provider_en_route', 'provider_arrived', 'vehicle_loading', 'trip_started', 'in_transit', 'vehicle_delivered', 'completed'] as $status) {
            $this->actingAsToken('POST', $this->driverToken, "/api/v1/drivers/me/orders/{$orderId}/status", ['status' => $status])
                ->assertOk();
        }

        return $orderId;
    }

    private function staffWithRole(RoleName $role): User
    {
        $user = User::factory()->admin()->create();
        $user->roles()->attach(Role::where('name', $role->value)->firstOrFail());

        return $user;
    }

    public function test_a_customer_can_review_their_own_completed_order_and_it_updates_ratings(): void
    {
        $this->seedActiveVersion();
        $providerId = $this->registerApprovedProviderWithAvailableTruck('+966501110160', '+966502220160');
        $orderId = $this->createCompletedOrder('+966503330160');

        $customer = User::query()->where('phone', '+966503330160')->firstOrFail();
        $response = $this->actingAsToken('POST', $this->tokenFor($customer), "/api/v1/customers/me/orders/{$orderId}/review", [
            'rating' => 4,
            'comment' => 'خدمة جيدة',
        ])->assertCreated();

        $this->assertSame(4, $response->json('data.review.rating'));

        $provider = Provider::query()->where('public_id', $providerId)->firstOrFail();
        $this->assertSame('4.00', $provider->fresh()->rating);

        $driver = $provider->drivers()->firstOrFail();
        $this->assertSame('4.00', $driver->fresh()->rating);
    }

    public function test_a_customer_cannot_review_an_order_that_is_not_completed(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110161', '+966502220161');
        $orderId = $this->createOrder('+966503330161');

        $customer = User::query()->where('phone', '+966503330161')->firstOrFail();
        $this->actingAsToken('POST', $this->tokenFor($customer), "/api/v1/customers/me/orders/{$orderId}/review", [
            'rating' => 5,
        ])->assertStatus(422)->assertJsonPath('errors.0.code', 'ORDER_NOT_REVIEWABLE');
    }

    public function test_a_customer_cannot_review_the_same_order_twice(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110162', '+966502220162');
        $orderId = $this->createCompletedOrder('+966503330162');

        $customer = User::query()->where('phone', '+966503330162')->firstOrFail();
        $token = $this->tokenFor($customer);

        $this->actingAsToken('POST', $token, "/api/v1/customers/me/orders/{$orderId}/review", ['rating' => 5])->assertCreated();
        $this->actingAsToken('POST', $token, "/api/v1/customers/me/orders/{$orderId}/review", ['rating' => 3])
            ->assertStatus(422)->assertJsonPath('errors.0.code', 'REVIEW_ALREADY_EXISTS');
    }

    public function test_the_provider_and_admin_can_view_reviews_about_the_provider(): void
    {
        $this->seedActiveVersion();
        $providerId = $this->registerApprovedProviderWithAvailableTruck('+966501110163', '+966502220163');
        $orderId = $this->createCompletedOrder('+966503330163');

        $customer = User::query()->where('phone', '+966503330163')->firstOrFail();
        $this->actingAsToken('POST', $this->tokenFor($customer), "/api/v1/customers/me/orders/{$orderId}/review", ['rating' => 5])
            ->assertCreated();

        $ownReviews = $this->actingAsToken('GET', $this->ownerToken, '/api/v1/providers/me/reviews')->assertOk();
        $this->assertCount(1, $ownReviews->json('data.reviews'));

        $complianceOfficer = $this->staffWithRole(RoleName::ComplianceOfficer);
        $adminReviews = $this->actingAsToken('GET', $this->tokenFor($complianceOfficer), "/api/v1/admin/providers/{$providerId}/reviews")
            ->assertOk();
        $this->assertCount(1, $adminReviews->json('data.reviews'));
    }
}
