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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
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

    private function registerApprovedProviderWithAvailableTruck(string $ownerPhone, string $driverPhone): void
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
     * @return array{0: string, 1: string} payment public id, gateway_payment_id
     */
    private function createPendingCardPayment(string $customerPhone): array
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

        $response = $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/payments", [
            'method' => 'mada',
        ]);

        $paymentId = $response->json('data.payment.id');
        $payment = Payment::query()->where('public_id', $paymentId)->firstOrFail();

        return [$paymentId, (string) $payment->gateway_payment_id];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postWebhook(array $payload, ?string $signatureOverride = null): TestResponse
    {
        $secret = (string) config('services.payments.fake.webhook_secret');
        $rawBody = json_encode($payload);
        $signature = $signatureOverride ?? hash_hmac('sha256', (string) $rawBody, $secret);

        return $this->postJson('/api/v1/webhooks/payments/fake', $payload, [
            'X-Fake-Signature' => $signature,
        ]);
    }

    public function test_a_valid_captured_event_captures_the_payment_and_sets_the_orders_final_price(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110110', '+966502220110');
        [$paymentId, $gatewayPaymentId] = $this->createPendingCardPayment('+966503330110');

        $payment = Payment::query()->where('public_id', $paymentId)->firstOrFail();

        $response = $this->postWebhook([
            'event_id' => 'evt_1',
            'event_type' => 'payment.captured',
            'gateway_payment_id' => $gatewayPaymentId,
            'status' => 'captured',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payments', ['public_id' => $paymentId, 'status' => 'captured']);

        $order = Order::query()->find($payment->order_id);
        $this->assertSame($payment->amount, $order->final_price);
    }

    public function test_a_valid_failed_event_marks_the_payment_failed(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110111', '+966502220111');
        [$paymentId, $gatewayPaymentId] = $this->createPendingCardPayment('+966503330111');

        $response = $this->postWebhook([
            'event_id' => 'evt_2',
            'event_type' => 'payment.failed',
            'gateway_payment_id' => $gatewayPaymentId,
            'status' => 'failed',
            'failure_reason' => 'insufficient_funds',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payments', [
            'public_id' => $paymentId,
            'status' => 'failed',
            'failure_reason' => 'insufficient_funds',
        ]);
    }

    public function test_an_invalid_signature_is_rejected(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110112', '+966502220112');
        [, $gatewayPaymentId] = $this->createPendingCardPayment('+966503330112');

        $response = $this->postWebhook([
            'event_id' => 'evt_3',
            'event_type' => 'payment.captured',
            'gateway_payment_id' => $gatewayPaymentId,
            'status' => 'captured',
        ], signatureOverride: 'not-the-right-signature');

        $response->assertStatus(401);
        $response->assertJsonPath('errors.0.code', 'WEBHOOK_SIGNATURE_INVALID');
    }

    public function test_a_duplicate_event_id_is_processed_only_once(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110113', '+966502220113');
        [$paymentId, $gatewayPaymentId] = $this->createPendingCardPayment('+966503330113');

        $payload = [
            'event_id' => 'evt_4',
            'event_type' => 'payment.captured',
            'gateway_payment_id' => $gatewayPaymentId,
            'status' => 'captured',
        ];

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertDatabaseHas('payments', ['public_id' => $paymentId, 'status' => 'captured']);
    }

    public function test_an_unknown_gateway_payment_id_does_not_error(): void
    {
        $response = $this->postWebhook([
            'event_id' => 'evt_5',
            'event_type' => 'payment.captured',
            'gateway_payment_id' => 'fake_does_not_exist',
            'status' => 'captured',
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }

    public function test_an_unknown_gateway_name_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/webhooks/payments/not-a-real-gateway', []);

        $response->assertStatus(404);
    }
}
