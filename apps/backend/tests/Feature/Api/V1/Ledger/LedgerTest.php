<?php

namespace Tests\Feature\Api\V1\Ledger;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LedgerTest extends TestCase
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

    private function payWithCash(string $orderId, string $customerToken): string
    {
        return $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/payments", [
            'method' => 'cash',
        ])->json('data.payment.id');
    }

    /**
     * @return array{0: string, 1: string} payment public id, gateway_payment_id
     */
    private function payWithCardPending(string $orderId, string $customerToken): array
    {
        $response = $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/payments", [
            'method' => 'mada',
        ]);
        $paymentId = $response->json('data.payment.id');
        $payment = Payment::query()->where('public_id', $paymentId)->firstOrFail();

        return [$paymentId, (string) $payment->gateway_payment_id];
    }

    private function confirmCardCapture(string $gatewayPaymentId, string $eventId = 'evt_capture'): void
    {
        $payload = [
            'event_id' => $eventId,
            'event_type' => 'payment.captured',
            'gateway_payment_id' => $gatewayPaymentId,
            'status' => 'captured',
        ];
        $secret = (string) config('services.payments.fake.webhook_secret');
        $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

        $this->postJson('/api/v1/webhooks/payments/fake', $payload, ['X-Fake-Signature' => $signature])
            ->assertOk();
    }

    private function staffWithRole(RoleName $role): User
    {
        $user = User::factory()->admin()->create();
        $user->roles()->attach(Role::where('name', $role->value)->firstOrFail());

        return $user;
    }

    public function test_a_cash_payment_makes_the_provider_owe_the_platform_its_commission(): void
    {
        $this->seedActiveVersion();
        $providerId = $this->registerApprovedProviderWithAvailableTruck('+966501110140', '+966502220140');
        $orderId = $this->createCompletedOrder('+966503330140');

        $customer = User::query()->where('phone', '+966503330140')->firstOrFail();
        $paymentId = $this->payWithCash($orderId, $this->tokenFor($customer));

        $order = Order::query()->where('public_id', $orderId)->firstOrFail();
        $snapshot = $order->pricing_snapshot;
        $commission = (int) $snapshot['platform_service_fee'];
        $tax = (int) $snapshot['vat_amount'];
        $gross = (int) $snapshot['total'];

        $this->assertDatabaseHas('ledger_entries', [
            'type' => 'customer_payment',
            'direction' => 'credit',
            'amount' => $gross,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'type' => 'platform_commission',
            'direction' => 'debit',
            'amount' => $commission,
        ]);
        $this->assertDatabaseMissing('ledger_entries', ['type' => 'gateway_fee']);
        $this->assertDatabaseHas('ledger_entries', [
            'type' => 'provider_payable',
            'direction' => 'debit',
            'amount' => $commission + $tax,
        ]);

        $provider = Provider::query()->where('public_id', $providerId)->firstOrFail();
        $balance = $this->actingAsToken('GET', $this->ownerToken, '/api/v1/providers/me/balance')->json('data.balance');

        $this->assertSame(-($commission + $tax), $balance['total_payable']);
        $this->assertNotSame($paymentId, null);
    }

    public function test_a_card_payment_pays_the_provider_net_of_commission_and_gateway_fee(): void
    {
        config(['services.payments.gateway_fee_percentage' => 0.02]);

        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110141', '+966502220141');
        $orderId = $this->createCompletedOrder('+966503330141');

        $customer = User::query()->where('phone', '+966503330141')->firstOrFail();
        [, $gatewayPaymentId] = $this->payWithCardPending($orderId, $this->tokenFor($customer));
        $this->confirmCardCapture($gatewayPaymentId);

        $order = Order::query()->where('public_id', $orderId)->firstOrFail();
        $snapshot = $order->pricing_snapshot;
        $commission = (int) $snapshot['platform_service_fee'];
        $tax = (int) $snapshot['vat_amount'];
        $gross = (int) $snapshot['total'];
        $gatewayFee = (int) round($gross * 0.02);
        $expectedPayable = $gross - $commission - $gatewayFee - $tax;

        $this->assertDatabaseHas('ledger_entries', ['type' => 'gateway_fee', 'direction' => 'debit', 'amount' => $gatewayFee]);
        $this->assertDatabaseHas('ledger_entries', [
            'type' => 'provider_payable',
            'direction' => 'credit',
            'amount' => $expectedPayable,
        ]);
    }

    public function test_a_full_refund_fully_reverses_the_provider_payable_entry(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110142', '+966502220142');
        $orderId = $this->createCompletedOrder('+966503330142');

        $customer = User::query()->where('phone', '+966503330142')->firstOrFail();
        $paymentId = $this->payWithCash($orderId, $this->tokenFor($customer));
        $payment = Payment::query()->where('public_id', $paymentId)->firstOrFail();

        $financeOfficer = $this->staffWithRole(RoleName::FinanceOfficer);
        $this->actingAsToken('POST', $this->tokenFor($financeOfficer), "/api/v1/admin/payments/{$paymentId}/refund", [
            'amount' => $payment->amount,
        ])->assertOk();

        $order = Order::query()->where('public_id', $orderId)->firstOrFail();
        $snapshot = $order->pricing_snapshot;
        $commission = (int) $snapshot['platform_service_fee'];
        $tax = (int) $snapshot['vat_amount'];

        // The original cash entry was a debit of (commission + tax); a
        // full refund reverses it completely — a credit of the same size.
        $this->assertDatabaseHas('ledger_entries', [
            'type' => 'refund',
            'direction' => 'credit',
            'amount' => $commission + $tax,
        ]);

        $balance = $this->actingAsToken('GET', $this->ownerToken, '/api/v1/providers/me/balance')->json('data.balance');
        $this->assertSame(0, $balance['total_payable']);
    }

    public function test_provider_balance_splits_pending_and_available_by_the_hold_period(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110143', '+966502220143');
        $orderId = $this->createCompletedOrder('+966503330143');

        $customer = User::query()->where('phone', '+966503330143')->firstOrFail();
        $this->payWithCash($orderId, $this->tokenFor($customer));

        // Fresh earnings sit in `pending_balance` within the hold window.
        $fresh = $this->actingAsToken('GET', $this->ownerToken, '/api/v1/providers/me/balance')->json('data.balance');
        $this->assertNotSame(0, $fresh['pending_balance']);
        $this->assertSame(0, $fresh['available_balance']);

        // Once the hold period has passed, the same entries move to
        // `available_balance`.
        Carbon::setTestNow(now()->addHours((int) config('ledger.pending_hold_hours') + 1));
        $later = $this->actingAsToken('GET', $this->ownerToken, '/api/v1/providers/me/balance')->json('data.balance');
        $this->assertSame(0, $later['pending_balance']);
        $this->assertNotSame(0, $later['available_balance']);
        $this->assertSame($fresh['total_payable'], $later['total_payable']);

        Carbon::setTestNow();
    }

    public function test_a_provider_can_view_their_own_ledger_but_a_dispatcher_cannot_view_it_via_admin(): void
    {
        $this->seedActiveVersion();
        $providerId = $this->registerApprovedProviderWithAvailableTruck('+966501110144', '+966502220144');
        $orderId = $this->createCompletedOrder('+966503330144');

        $customer = User::query()->where('phone', '+966503330144')->firstOrFail();
        $this->payWithCash($orderId, $this->tokenFor($customer));

        $this->actingAsToken('GET', $this->ownerToken, '/api/v1/providers/me/ledger')->assertOk();

        $dispatcher = $this->staffWithRole(RoleName::Dispatcher);
        $this->actingAsToken('GET', $this->tokenFor($dispatcher), "/api/v1/admin/providers/{$providerId}/ledger")
            ->assertStatus(403);
    }

    public function test_finance_officer_can_view_any_providers_balance_and_ledger(): void
    {
        $this->seedActiveVersion();
        $providerId = $this->registerApprovedProviderWithAvailableTruck('+966501110145', '+966502220145');
        $orderId = $this->createCompletedOrder('+966503330145');

        $customer = User::query()->where('phone', '+966503330145')->firstOrFail();
        $this->payWithCash($orderId, $this->tokenFor($customer));

        $financeOfficer = $this->staffWithRole(RoleName::FinanceOfficer);
        $token = $this->tokenFor($financeOfficer);

        $this->actingAsToken('GET', $token, "/api/v1/admin/providers/{$providerId}/balance")->assertOk();
        $ledger = $this->actingAsToken('GET', $token, "/api/v1/admin/providers/{$providerId}/ledger");
        $ledger->assertOk();
        $this->assertGreaterThan(0, count($ledger->json('data.entries')));
    }
}
