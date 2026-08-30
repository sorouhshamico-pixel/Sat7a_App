<?php

namespace Tests\Feature\Api\V1\Orders;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderStateMachine;
use App\Domain\Pricing\Enums\VehicleCategory;
use App\Domain\Pricing\Models\PricingRuleVersion;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
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

    private function createOrderFor(string $token, string $vehicleId): string
    {
        $response = $this->withToken($token)->postJson('/api/v1/customers/me/orders', [
            'vehicle_id' => $vehicleId,
            'service_type' => ServiceCapability::StandardFlatbed->value,
            'vehicle_category' => VehicleCategory::Sedan->value,
            'pickup_latitude' => 24.7136,
            'pickup_longitude' => 46.6753,
            'pickup_formatted_address' => 'King Fahd Road, Riyadh',
            'dropoff_latitude' => 24.6408,
            'dropoff_longitude' => 46.7728,
            'dropoff_formatted_address' => 'Al Malaz, Riyadh',
        ]);

        return $response->json('data.order.id');
    }

    public function test_a_customer_can_cancel_a_pending_order(): void
    {
        $this->seedActiveVersion();

        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $vehicleId = $this->withToken($token)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $orderId = $this->createOrderFor($token, $vehicleId);

        $response = $this->withToken($token)->postJson("/api/v1/customers/me/orders/{$orderId}/cancel", [
            'reason' => 'Changed my mind',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.order.status', 'cancelled_by_customer');
        $response->assertJsonPath('data.order.cancelled_by', 'customer');

        // Dispatch (Phase 9) auto-runs the order to searching_provider
        // right after creation, so that's the status cancellation
        // transitions from here, not pending.
        $this->assertDatabaseHas('order_status_history', [
            'to_status' => 'cancelled_by_customer',
            'from_status' => 'searching_provider',
        ]);
    }

    public function test_a_customer_cannot_cancel_an_order_once_the_trip_has_started(): void
    {
        $this->seedActiveVersion();

        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $vehicleId = $this->withToken($token)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $orderId = $this->createOrderFor($token, $vehicleId);

        // Dispatch (Phase 9) has already auto-transitioned the order to
        // searching_provider by this point (see StartDispatchListener).
        $order = Order::query()->where('public_id', $orderId)->firstOrFail();
        $stateMachine = app(OrderStateMachine::class);
        $stateMachine->transition($order, OrderStatus::ProviderAssigned);
        $stateMachine->transition($order->fresh(), OrderStatus::ProviderEnRoute);
        $stateMachine->transition($order->fresh(), OrderStatus::ProviderArrived);
        $stateMachine->transition($order->fresh(), OrderStatus::VehicleLoading);
        $stateMachine->transition($order->fresh(), OrderStatus::TripStarted);

        $response = $this->withToken($token)->postJson("/api/v1/customers/me/orders/{$orderId}/cancel");

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'ORDER_NOT_CANCELLABLE');
    }

    public function test_a_customer_cannot_cancel_another_customers_order(): void
    {
        $this->seedActiveVersion();

        $tokenA = $this->tokenFor($this->authenticateNewCustomer('+966501234567'));
        $vehicleId = $this->withToken($tokenA)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');
        $orderId = $this->createOrderFor($tokenA, $vehicleId);

        $tokenB = $this->tokenFor($this->authenticateNewCustomer('+966501234599'));

        $response = $this->actingAsToken('POST', $tokenB, "/api/v1/customers/me/orders/{$orderId}/cancel");

        $response->assertStatus(404);
    }

    public function test_a_customer_can_view_their_orders_timeline(): void
    {
        $this->seedActiveVersion();

        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $vehicleId = $this->withToken($token)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $orderId = $this->createOrderFor($token, $vehicleId);

        $response = $this->withToken($token)->getJson("/api/v1/customers/me/orders/{$orderId}/timeline");

        $response->assertOk();
        // pending (creation) + searching_provider (auto-dispatch, Phase 9).
        $this->assertCount(2, $response->json('data.timeline'));
        $response->assertJsonPath('data.timeline.0.to_status', 'pending');
        $response->assertJsonPath('data.timeline.1.to_status', 'searching_provider');
    }

    public function test_a_customer_can_list_their_order_history(): void
    {
        $this->seedActiveVersion();

        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $vehicleId = $this->withToken($token)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $this->createOrderFor($token, $vehicleId);
        $this->createOrderFor($token, $vehicleId);

        $response = $this->withToken($token)->getJson('/api/v1/customers/me/orders');

        $response->assertOk();
        $orders = $response->json('data.orders');
        $this->assertCount(2, $orders);

        // Real Phase 24 finding (docs/PERFORMANCE.md): this list endpoint
        // didn't eager-load `vehicle`, so OrderResource's `vehicle` field
        // silently came back missing from every order on this screen even
        // though customers/me/orders/{id} (show) always had it.
        $this->assertSame('Toyota', $orders[0]['vehicle']['make']);
        $this->assertSame('Camry', $orders[0]['vehicle']['model']);
    }
}
