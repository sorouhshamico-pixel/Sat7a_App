<?php

namespace Tests\Feature\Api\V1\Payments;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
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

class PaymentCreationTest extends TestCase
{
    use RefreshDatabase;

    private const PICKUP_LAT = 24.7136;

    private const PICKUP_LNG = 46.6753;

    private string $driverToken;

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

        $driverUser = $provider->drivers()->where('public_id', $driverId)->firstOrFail()->user;
        $this->driverToken = $this->tokenFor($driverUser);

        return $truckId;
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
     * Creates an order, assigns it to the already-registered driver, and
     * advances it all the way to `completed` — the only statuses a
     * payment can be created for (see OrderStatus::isPayable()).
     *
     * @return array{0: string, 1: string} order public id, customer token
     */
    private function createCompletedOrder(string $customerPhone): array
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

        $offerId = $this->actingAsToken('GET', $this->driverToken, '/api/v1/drivers/me/dispatch-offers')
            ->json('data.offers.0.id');
        $this->actingAsToken('POST', $this->driverToken, "/api/v1/drivers/me/dispatch-offers/{$offerId}/accept")->assertOk();

        foreach (['provider_en_route', 'provider_arrived', 'vehicle_loading', 'trip_started', 'in_transit', 'vehicle_delivered', 'completed'] as $status) {
            $this->actingAsToken('POST', $this->driverToken, "/api/v1/drivers/me/orders/{$orderId}/status", ['status' => $status])
                ->assertOk();
        }

        return [$orderId, $customerToken];
    }

    public function test_a_cash_payment_is_captured_immediately(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110100', '+966502220100');
        [$orderId, $customerToken] = $this->createCompletedOrder('+966503330100');

        $response = $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/payments", [
            'method' => 'cash',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.payment.status', 'captured');
        $response->assertJsonPath('data.payment.method', 'cash');
        $this->assertNull($response->json('data.redirect_url'));

        $this->assertDatabaseHas('orders', ['public_id' => $orderId, 'final_price' => $response->json('data.payment.amount')]);
    }

    public function test_a_card_payment_is_pending_with_a_redirect_url(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110101', '+966502220101');
        [$orderId, $customerToken] = $this->createCompletedOrder('+966503330101');

        $response = $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/payments", [
            'method' => 'mada',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.payment.status', 'pending');
        $response->assertJsonPath('data.payment.card_last_four', '4242');
        $this->assertNotNull($response->json('data.redirect_url'));
    }

    public function test_a_payment_cannot_be_created_before_the_order_is_deliverable(): void
    {
        $this->seedActiveVersion();
        $customer = $this->authenticateNewCustomer('+966503330102');
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

        $response = $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/payments", [
            'method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'ORDER_NOT_PAYABLE');
    }

    public function test_a_second_payment_attempt_is_blocked_once_one_has_captured(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110103', '+966502220103');
        [$orderId, $customerToken] = $this->createCompletedOrder('+966503330103');

        $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/payments", [
            'method' => 'cash',
        ])->assertCreated();

        $response = $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/payments", [
            'method' => 'cash',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('errors.0.code', 'PAYMENT_ALREADY_ACTIVE');
    }

    public function test_an_idempotency_key_replay_returns_the_same_payment(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110104', '+966502220104');
        [$orderId, $customerToken] = $this->createCompletedOrder('+966503330104');

        $first = $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/payments", [
            'method' => 'mada',
        ], ['Idempotency-Key' => 'test-key-123']);

        $second = $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/payments", [
            'method' => 'mada',
        ], ['Idempotency-Key' => 'test-key-123']);

        $first->assertCreated();
        $second->assertCreated();
        $this->assertSame($first->json('data.payment.id'), $second->json('data.payment.id'));

        $order = Order::query()->where('public_id', $orderId)->firstOrFail();
        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());
    }

    public function test_a_different_customer_cannot_pay_for_someone_elses_order(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110105', '+966502220105');
        [$orderId] = $this->createCompletedOrder('+966503330105');

        $stranger = $this->authenticateNewCustomer('+966503330106');

        $response = $this->actingAsToken('POST', $this->tokenFor($stranger), "/api/v1/customers/me/orders/{$orderId}/payments", [
            'method' => 'cash',
        ]);

        $response->assertStatus(404);
    }

    public function test_payment_creation_validates_the_method(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110107', '+966502220107');
        [$orderId, $customerToken] = $this->createCompletedOrder('+966503330107');

        $response = $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/payments", [
            'method' => 'bitcoin',
        ]);

        $response->assertStatus(422);
    }
}
