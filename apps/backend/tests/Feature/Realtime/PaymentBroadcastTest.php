<?php

namespace Tests\Feature\Realtime;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Payments\Events\PaymentCaptured;
use App\Domain\Payments\Events\PaymentFailed;
use App\Domain\Payments\Models\Payment;
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

class PaymentBroadcastTest extends TestCase
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

    private function createCompletedOrder(string $customerPhone): string
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

        return $orderId;
    }

    public function test_a_captured_cash_payment_broadcasts_on_the_orders_channel(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110130', '+966502220130');
        $orderId = $this->createCompletedOrder('+966503330130');

        $customer = User::query()->where('phone', '+966503330130')->firstOrFail();
        $customerToken = $this->tokenFor($customer);

        Event::fake([PaymentCaptured::class]);

        $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/payments", [
            'method' => 'cash',
        ])->assertCreated();

        Event::assertDispatched(PaymentCaptured::class, function (PaymentCaptured $event) use ($orderId) {
            $channels = $event->broadcastOn();

            return $channels[0]->name === "private-orders.{$orderId}";
        });
    }

    public function test_a_failed_webhook_event_broadcasts_payment_failed(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110131', '+966502220131');
        $orderId = $this->createCompletedOrder('+966503330131');

        $customer = User::query()->where('phone', '+966503330131')->firstOrFail();
        $customerToken = $this->tokenFor($customer);

        $gatewayPaymentId = $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/payments", [
            'method' => 'visa',
        ])->json('data.payment.id');

        $payment = Payment::query()->where('public_id', $gatewayPaymentId)->firstOrFail();

        Event::fake([PaymentFailed::class]);

        $payload = [
            'event_id' => 'evt_broadcast_1',
            'event_type' => 'payment.failed',
            'gateway_payment_id' => $payment->gateway_payment_id,
            'status' => 'failed',
            'failure_reason' => 'card_declined',
        ];
        $secret = (string) config('services.payments.fake.webhook_secret');
        $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

        $this->postJson('/api/v1/webhooks/payments/fake', $payload, ['X-Fake-Signature' => $signature])
            ->assertOk();

        Event::assertDispatched(PaymentFailed::class, function (PaymentFailed $event) use ($orderId) {
            $channels = $event->broadcastOn();

            return $channels[0]->name === "private-orders.{$orderId}";
        });
    }
}
