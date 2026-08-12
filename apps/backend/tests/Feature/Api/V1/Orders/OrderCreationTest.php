<?php

namespace Tests\Feature\Api\V1\Orders;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Pricing\Enums\VehicleCategory;
use App\Domain\Pricing\Models\PricingRuleVersion;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateNewCustomer(string $phone = '+966501234567'): User
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
     * @return array<string, mixed>
     */
    private function orderPayload(string $vehicleId): array
    {
        return [
            'vehicle_id' => $vehicleId,
            'service_type' => ServiceCapability::StandardFlatbed->value,
            'vehicle_category' => VehicleCategory::Sedan->value,
            'pickup_latitude' => 24.7136,
            'pickup_longitude' => 46.6753,
            'pickup_formatted_address' => 'King Fahd Road, Riyadh',
            'dropoff_latitude' => 24.6408,
            'dropoff_longitude' => 46.7728,
            'dropoff_formatted_address' => 'Al Malaz, Riyadh',
        ];
    }

    public function test_a_customer_can_create_an_order_with_a_server_computed_price(): void
    {
        $this->seedActiveVersion();

        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $vehicleId = $this->withToken($token)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $response = $this->withToken($token)->postJson('/api/v1/customers/me/orders', $this->orderPayload($vehicleId));

        $response->assertCreated();
        // Dispatch (Phase 9) auto-runs right after creation — with no
        // eligible trucks in this test, it lands on searching_provider
        // with manual_dispatch_required flagged (see
        // docs/DISPATCH_ENGINE.md and tests/Feature/Api/V1/Dispatch/).
        $response->assertJsonPath('data.order.status', 'searching_provider');
        $this->assertGreaterThan(0, $response->json('data.order.quoted_price'));
        $this->assertSame('cash', $response->json('data.order.payment_method'));

        $this->assertDatabaseHas('order_status_history', [
            'to_status' => 'pending',
            'from_status' => null,
        ]);
        $this->assertDatabaseHas('order_status_history', [
            'to_status' => 'searching_provider',
            'from_status' => 'pending',
        ]);
    }

    public function test_a_client_supplied_price_is_ignored_and_never_trusted(): void
    {
        $this->seedActiveVersion();

        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $vehicleId = $this->withToken($token)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $payload = $this->orderPayload($vehicleId);
        $payload['quoted_price'] = 1;
        $payload['total'] = 1;

        $response = $this->withToken($token)->postJson('/api/v1/customers/me/orders', $payload);

        $response->assertCreated();
        $this->assertNotSame(1, $response->json('data.order.quoted_price'));
    }

    public function test_creating_an_order_fails_gracefully_without_active_pricing(): void
    {
        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $vehicleId = $this->withToken($token)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $response = $this->withToken($token)->postJson('/api/v1/customers/me/orders', $this->orderPayload($vehicleId));

        $response->assertStatus(503);
        $response->assertJsonPath('errors.0.code', 'PRICING_UNAVAILABLE');
    }

    public function test_a_customer_cannot_create_an_order_for_another_customers_vehicle(): void
    {
        $this->seedActiveVersion();

        $ownerToken = $this->tokenFor($this->authenticateNewCustomer('+966501234567'));
        $vehicleId = $this->withToken($ownerToken)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $otherToken = $this->tokenFor($this->authenticateNewCustomer('+966501234599'));

        $response = $this->actingAsToken('POST', $otherToken, '/api/v1/customers/me/orders', $this->orderPayload($vehicleId));

        $response->assertStatus(404);
        $response->assertJsonPath('errors.0.code', 'VEHICLE_NOT_FOUND');
    }

    public function test_requires_manual_quote_flag_is_rejected_with_a_clear_error(): void
    {
        $this->seedActiveVersion();

        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $vehicleId = $this->withToken($token)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $payload = $this->orderPayload($vehicleId);
        $payload['requires_manual_quote'] = true;

        $response = $this->withToken($token)->postJson('/api/v1/customers/me/orders', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'MANUAL_QUOTE_REQUIRED');
    }

    public function test_order_creation_is_rate_limited(): void
    {
        $this->seedActiveVersion();

        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $vehicleId = $this->withToken($token)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        for ($i = 0; $i < 5; $i++) {
            $this->withToken($token)->postJson('/api/v1/customers/me/orders', $this->orderPayload($vehicleId));
        }

        $response = $this->withToken($token)->postJson('/api/v1/customers/me/orders', $this->orderPayload($vehicleId));

        $response->assertStatus(429);
    }

    public function test_order_creation_validates_required_fields(): void
    {
        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/v1/customers/me/orders', [])->assertStatus(422);
    }

    public function test_a_guest_cannot_create_an_order(): void
    {
        $this->postJson('/api/v1/customers/me/orders', [])->assertStatus(401);
    }
}
