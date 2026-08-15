<?php

namespace Tests\Feature\Api\V1\Disputes;

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

class DisputeTest extends TestCase
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

    public function test_a_customer_can_raise_a_dispute_on_a_completed_order(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110170', '+966502220170');
        $orderId = $this->createCompletedOrder('+966503330170');

        $customer = User::query()->where('phone', '+966503330170')->firstOrFail();
        $response = $this->actingAsToken('POST', $this->tokenFor($customer), "/api/v1/customers/me/orders/{$orderId}/dispute", [
            'reason' => 'overcharge',
            'description' => 'تم خصم مبلغ أكبر من المتفق عليه',
        ])->assertCreated();

        $this->assertSame('open', $response->json('data.dispute.status'));

        $listed = $this->actingAsToken('GET', $this->tokenFor($customer), '/api/v1/customers/me/disputes')->assertOk();
        $this->assertCount(1, $listed->json('data.disputes'));
    }

    public function test_a_customer_cannot_dispute_an_order_that_is_still_in_progress(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110171', '+966502220171');
        $orderId = $this->createOrder('+966503330171');

        $customer = User::query()->where('phone', '+966503330171')->firstOrFail();
        $this->actingAsToken('POST', $this->tokenFor($customer), "/api/v1/customers/me/orders/{$orderId}/dispute", [
            'reason' => 'other',
            'description' => 'مشكلة في الطلب',
        ])->assertStatus(422)->assertJsonPath('errors.0.code', 'ORDER_NOT_DISPUTABLE');
    }

    public function test_a_customer_cannot_raise_a_second_open_dispute_on_the_same_order(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110172', '+966502220172');
        $orderId = $this->createCompletedOrder('+966503330172');

        $customer = User::query()->where('phone', '+966503330172')->firstOrFail();
        $token = $this->tokenFor($customer);

        $this->actingAsToken('POST', $token, "/api/v1/customers/me/orders/{$orderId}/dispute", [
            'reason' => 'damage', 'description' => 'تلف في السيارة',
        ])->assertCreated();

        $this->actingAsToken('POST', $token, "/api/v1/customers/me/orders/{$orderId}/dispute", [
            'reason' => 'other', 'description' => 'مشكلة أخرى',
        ])->assertStatus(422)->assertJsonPath('errors.0.code', 'DISPUTE_ALREADY_OPEN');
    }

    public function test_customer_support_can_pick_up_and_resolve_a_dispute(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110173', '+966502220173');
        $orderId = $this->createCompletedOrder('+966503330173');

        $customer = User::query()->where('phone', '+966503330173')->firstOrFail();
        $disputeId = $this->actingAsToken('POST', $this->tokenFor($customer), "/api/v1/customers/me/orders/{$orderId}/dispute", [
            'reason' => 'service_quality', 'description' => 'جودة الخدمة سيئة',
        ])->json('data.dispute.id');

        $support = $this->staffWithRole(RoleName::CustomerSupport);
        $supportToken = $this->tokenFor($support);

        $this->actingAsToken('POST', $supportToken, "/api/v1/admin/disputes/{$disputeId}/status", ['status' => 'under_review'])
            ->assertOk()
            ->assertJsonPath('data.dispute.status', 'under_review');

        $this->actingAsToken('POST', $supportToken, "/api/v1/admin/disputes/{$disputeId}/status", [
            'status' => 'resolved',
            'resolution_notes' => 'تم رد المبلغ للعميل',
        ])->assertOk()->assertJsonPath('data.dispute.status', 'resolved');
    }

    public function test_resolving_a_dispute_without_resolution_notes_fails(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110174', '+966502220174');
        $orderId = $this->createCompletedOrder('+966503330174');

        $customer = User::query()->where('phone', '+966503330174')->firstOrFail();
        $disputeId = $this->actingAsToken('POST', $this->tokenFor($customer), "/api/v1/customers/me/orders/{$orderId}/dispute", [
            'reason' => 'no_show', 'description' => 'لم يصل السائق',
        ])->json('data.dispute.id');

        $support = $this->staffWithRole(RoleName::CustomerSupport);
        $supportToken = $this->tokenFor($support);

        $this->actingAsToken('POST', $supportToken, "/api/v1/admin/disputes/{$disputeId}/status", ['status' => 'under_review'])->assertOk();

        $this->actingAsToken('POST', $supportToken, "/api/v1/admin/disputes/{$disputeId}/status", ['status' => 'rejected'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'DISPUTE_RESOLUTION_NOTES_REQUIRED');
    }

    public function test_a_dispatcher_without_disputes_permission_cannot_view_disputes(): void
    {
        $dispatcher = $this->staffWithRole(RoleName::Dispatcher);
        $this->actingAsToken('GET', $this->tokenFor($dispatcher), '/api/v1/admin/disputes')->assertStatus(403);
    }
}
