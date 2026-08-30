<?php

namespace Tests\Feature\Api\V1\Ledger;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Domain\Ledger\Models\LedgerEntry;
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

class SettlementTest extends TestCase
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
        config(['services.payments.gateway_fee_percentage' => 0.02]);
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

    /**
     * @return array{0: string, 1: string} providerId, orderId — a card-paid,
     *                                     completed order past the pending-hold window, ready to settle.
     */
    private function setUpProviderWithSettleableEarnings(string $suffix): array
    {
        $this->seedActiveVersion();
        $providerId = $this->registerApprovedProviderWithAvailableTruck("+96650111{$suffix}", "+96650222{$suffix}");
        $orderId = $this->createCompletedOrder("+96650333{$suffix}");

        $customer = User::query()->where('phone', "+96650333{$suffix}")->firstOrFail();
        [, $gatewayPaymentId] = $this->payWithCardPending($orderId, $this->tokenFor($customer));
        $this->confirmCardCapture($gatewayPaymentId, "evt_{$suffix}");

        Carbon::setTestNow(now()->addHours((int) config('ledger.pending_hold_hours') + 1));

        return [$providerId, $orderId];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_generating_a_settlement_batch_claims_eligible_entries_and_computes_net(): void
    {
        [$providerId] = $this->setUpProviderWithSettleableEarnings('0150');

        $financeOfficer = $this->staffWithRole(RoleName::FinanceOfficer);
        $balanceBefore = $this->actingAsToken('GET', $this->tokenFor($financeOfficer), "/api/v1/admin/providers/{$providerId}/balance")
            ->json('data.balance');

        $response = $this->actingAsToken('POST', $this->tokenFor($financeOfficer), "/api/v1/admin/providers/{$providerId}/settlements", [
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertCreated();

        $batch = $response->json('data.settlement');
        $this->assertSame($balanceBefore['available_balance'], $batch['net']);
        $this->assertSame('draft', $batch['status']);
        $this->assertGreaterThan(0, $batch['net']);

        // Only the balance-affecting entries (provider_payable/refund/
        // adjustment) get claimed — the customer_payment/platform_commission/
        // gateway_fee reporting lines never do (see LedgerEntryType::affectsProviderBalance()).
        $provider = Provider::query()->where('public_id', $providerId)->firstOrFail();
        $this->assertSame(
            0,
            LedgerEntry::query()
                ->where('provider_id', $provider->id)
                ->where('type', LedgerEntryType::ProviderPayable)
                ->whereNull('settlement_batch_id')
                ->count(),
        );
    }

    public function test_generating_a_batch_with_no_eligible_earnings_fails(): void
    {
        $this->seedActiveVersion();
        $providerId = $this->registerApprovedProviderWithAvailableTruck('+966501110151', '+966502220151');

        $financeOfficer = $this->staffWithRole(RoleName::FinanceOfficer);
        $this->actingAsToken('POST', $this->tokenFor($financeOfficer), "/api/v1/admin/providers/{$providerId}/settlements", [
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'NO_ELIGIBLE_EARNINGS');
    }

    public function test_the_full_lifecycle_reaches_paid_records_a_settlement_entry_and_zeroes_available_balance(): void
    {
        [$providerId] = $this->setUpProviderWithSettleableEarnings('0152');

        $this->actingAsToken('PUT', $this->ownerToken, '/api/v1/providers/me/bank-account', [
            'account_holder_name' => 'محمد أحمد',
            'iban' => 'SA0380000000608010167519',
            'bank_name' => 'Al Rajhi Bank',
        ])->assertOk();

        $financeOfficer = $this->staffWithRole(RoleName::FinanceOfficer);
        $financeToken = $this->tokenFor($financeOfficer);

        $bankAccountId = $this->actingAsToken('GET', $financeToken, "/api/v1/admin/providers/{$providerId}/bank-account")
            ->json('data.bank_account.id');
        $this->actingAsToken('POST', $financeToken, "/api/v1/admin/providers/{$providerId}/bank-account/verify")->assertOk();

        $batch = $this->actingAsToken('POST', $financeToken, "/api/v1/admin/providers/{$providerId}/settlements", [
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->json('data.settlement');
        $net = $batch['net'];

        foreach (['pending_approval', 'approved', 'processing'] as $status) {
            $this->actingAsToken('POST', $financeToken, "/api/v1/admin/settlements/{$batch['id']}/status", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('data.settlement.status', $status);
        }

        $this->actingAsToken('POST', $financeToken, "/api/v1/admin/settlements/{$batch['id']}/status", [
            'status' => 'paid',
            'reference' => 'PAY-REF-0152',
        ])->assertOk()->assertJsonPath('data.settlement.status', 'paid');

        $this->assertDatabaseHas('ledger_entries', [
            'type' => 'settlement',
            'direction' => 'debit',
            'amount' => $net,
        ]);

        $balance = $this->actingAsToken('GET', $financeToken, "/api/v1/admin/providers/{$providerId}/balance")->json('data.balance');
        $this->assertSame(0, $balance['available_balance']);
        $this->assertSame($net, $balance['settled_balance']);
        $this->assertSame(0, $balance['total_payable']);
        $this->assertNotNull($bankAccountId);
    }

    public function test_a_batch_cannot_be_marked_paid_without_a_verified_bank_account(): void
    {
        [$providerId] = $this->setUpProviderWithSettleableEarnings('0153');

        $financeOfficer = $this->staffWithRole(RoleName::FinanceOfficer);
        $financeToken = $this->tokenFor($financeOfficer);

        $batch = $this->actingAsToken('POST', $financeToken, "/api/v1/admin/providers/{$providerId}/settlements", [
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->json('data.settlement');

        foreach (['pending_approval', 'approved', 'processing'] as $status) {
            $this->actingAsToken('POST', $financeToken, "/api/v1/admin/settlements/{$batch['id']}/status", ['status' => $status])->assertOk();
        }

        $this->actingAsToken('POST', $financeToken, "/api/v1/admin/settlements/{$batch['id']}/status", ['status' => 'paid'])
            ->assertStatus(404)
            ->assertJsonPath('errors.0.code', 'BANK_ACCOUNT_NOT_FOUND');
    }

    public function test_cancelling_a_draft_batch_releases_its_claimed_entries(): void
    {
        [$providerId] = $this->setUpProviderWithSettleableEarnings('0154');
        $provider = Provider::query()->where('public_id', $providerId)->firstOrFail();

        $financeOfficer = $this->staffWithRole(RoleName::FinanceOfficer);
        $financeToken = $this->tokenFor($financeOfficer);

        $batch = $this->actingAsToken('POST', $financeToken, "/api/v1/admin/providers/{$providerId}/settlements", [
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->json('data.settlement');

        $this->assertGreaterThan(0, LedgerEntry::query()->where('provider_id', $provider->id)->whereNotNull('settlement_batch_id')->count());

        $this->actingAsToken('POST', $financeToken, "/api/v1/admin/settlements/{$batch['id']}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.settlement.status', 'cancelled');

        $this->assertSame(
            0,
            LedgerEntry::query()->where('provider_id', $provider->id)->whereNotNull('settlement_batch_id')->count(),
        );
    }

    /**
     * Real Phase 24 finding (docs/PERFORMANCE.md): neither
     * App\Http\Controllers\Api\V1\Providers\SettlementController::index()
     * nor ::show() eager-loaded `approvedBy`, so SettlementBatchResource's
     * `approved_by` field silently came back null on every response a
     * provider ever saw of their own settlement — even after an admin had
     * genuinely approved it.
     */
    public function test_a_provider_sees_who_approved_their_settlement_batch(): void
    {
        [$providerId] = $this->setUpProviderWithSettleableEarnings('0156');

        $financeOfficer = $this->staffWithRole(RoleName::FinanceOfficer);
        $financeToken = $this->tokenFor($financeOfficer);

        $batch = $this->actingAsToken('POST', $financeToken, "/api/v1/admin/providers/{$providerId}/settlements", [
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->json('data.settlement');

        foreach (['pending_approval', 'approved'] as $status) {
            $this->actingAsToken('POST', $financeToken, "/api/v1/admin/settlements/{$batch['id']}/status", ['status' => $status])
                ->assertOk();
        }

        $shown = $this->actingAsToken('GET', $this->ownerToken, "/api/v1/providers/me/settlements/{$batch['id']}")
            ->assertOk()
            ->json('data.settlement');
        $this->assertSame($financeOfficer->public_id, $shown['approved_by']);

        $indexed = $this->actingAsToken('GET', $this->ownerToken, '/api/v1/providers/me/settlements')
            ->assertOk()
            ->json('data.settlements');
        $this->assertSame($financeOfficer->public_id, $indexed[0]['approved_by']);
    }

    public function test_a_dispatcher_cannot_generate_or_advance_settlements(): void
    {
        [$providerId] = $this->setUpProviderWithSettleableEarnings('0155');

        $dispatcher = $this->staffWithRole(RoleName::Dispatcher);
        $this->actingAsToken('POST', $this->tokenFor($dispatcher), "/api/v1/admin/providers/{$providerId}/settlements", [
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertStatus(403);
    }

    public function test_setting_a_bank_account_resets_verification_and_masks_iban_for_the_owner(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110156', '+966502220156');

        $this->actingAsToken('PUT', $this->ownerToken, '/api/v1/providers/me/bank-account', [
            'account_holder_name' => 'محمد أحمد',
            'iban' => 'SA0380000000608010167519',
            'bank_name' => 'Al Rajhi Bank',
        ])->assertOk()->assertJsonPath('data.bank_account.iban', 'SA0380000000608010167519');

        $financeOfficer = $this->staffWithRole(RoleName::FinanceOfficer);
        $provider = Provider::query()->where('contact_phone', '+966501110156')->firstOrFail();
        $this->actingAsToken('POST', $this->tokenFor($financeOfficer), "/api/v1/admin/providers/{$provider->public_id}/bank-account/verify")
            ->assertOk()
            ->assertJsonPath('data.bank_account.verified', true);

        // Re-saving the account resets verification.
        $this->actingAsToken('PUT', $this->ownerToken, '/api/v1/providers/me/bank-account', [
            'account_holder_name' => 'محمد أحمد',
            'iban' => 'SA1380000000608010167520',
            'bank_name' => 'Al Rajhi Bank',
        ])->assertOk()->assertJsonPath('data.bank_account.verified', false);
    }
}
