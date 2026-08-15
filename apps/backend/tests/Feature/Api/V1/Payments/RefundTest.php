<?php

namespace Tests\Feature\Api\V1\Payments;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
use App\Domain\Fleet\Enums\ServiceCapability;
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

class RefundTest extends TestCase
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

    private function createCapturedCashPayment(string $customerPhone): string
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

        return $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/payments", [
            'method' => 'cash',
        ])->json('data.payment.id');
    }

    private function staffWithRole(RoleName $role): User
    {
        $user = User::factory()->admin()->create();
        $user->roles()->attach(Role::where('name', $role->value)->firstOrFail());

        return $user;
    }

    public function test_finance_officer_can_fully_refund_a_captured_payment(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110120', '+966502220120');
        $paymentId = $this->createCapturedCashPayment('+966503330120');

        $payment = Payment::query()->where('public_id', $paymentId)->firstOrFail();

        $financeOfficer = $this->staffWithRole(RoleName::FinanceOfficer);
        $token = $this->tokenFor($financeOfficer);

        $response = $this->actingAsToken('POST', $token, "/api/v1/admin/payments/{$paymentId}/refund", [
            'amount' => $payment->amount,
            'reason' => 'Customer complaint',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.refund.status', 'succeeded');
        $response->assertJsonPath('data.payment.status', 'refunded');

        $this->assertDatabaseHas('audit_logs', ['action' => 'payments.refunded', 'entity_type' => 'payment']);
    }

    public function test_a_partial_refund_leaves_the_payment_partially_refunded(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110121', '+966502220121');
        $paymentId = $this->createCapturedCashPayment('+966503330121');

        $payment = Payment::query()->where('public_id', $paymentId)->firstOrFail();
        $partialAmount = intdiv($payment->amount, 2);

        $financeOfficer = $this->staffWithRole(RoleName::FinanceOfficer);
        $token = $this->tokenFor($financeOfficer);

        $response = $this->actingAsToken('POST', $token, "/api/v1/admin/payments/{$paymentId}/refund", [
            'amount' => $partialAmount,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.payment.status', 'partially_refunded');
        $response->assertJsonPath('data.payment.refunded_amount', $partialAmount);
    }

    public function test_a_refund_exceeding_the_available_amount_is_rejected(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110122', '+966502220122');
        $paymentId = $this->createCapturedCashPayment('+966503330122');

        $payment = Payment::query()->where('public_id', $paymentId)->firstOrFail();

        $financeOfficer = $this->staffWithRole(RoleName::FinanceOfficer);
        $token = $this->tokenFor($financeOfficer);

        $response = $this->actingAsToken('POST', $token, "/api/v1/admin/payments/{$paymentId}/refund", [
            'amount' => $payment->amount + 1000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'REFUND_EXCEEDS_AVAILABLE_AMOUNT');
    }

    public function test_a_dispatcher_cannot_refund_a_payment(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110123', '+966502220123');
        $paymentId = $this->createCapturedCashPayment('+966503330123');

        $payment = Payment::query()->where('public_id', $paymentId)->firstOrFail();

        $dispatcher = $this->staffWithRole(RoleName::Dispatcher);
        $token = $this->tokenFor($dispatcher);

        $response = $this->actingAsToken('POST', $token, "/api/v1/admin/payments/{$paymentId}/refund", [
            'amount' => $payment->amount,
        ]);

        $response->assertStatus(403);
    }

    public function test_a_dispatcher_cannot_view_payments_either(): void
    {
        $dispatcher = $this->staffWithRole(RoleName::Dispatcher);
        $token = $this->tokenFor($dispatcher);

        $this->actingAsToken('GET', $token, '/api/v1/admin/payments')->assertStatus(403);
    }
}
