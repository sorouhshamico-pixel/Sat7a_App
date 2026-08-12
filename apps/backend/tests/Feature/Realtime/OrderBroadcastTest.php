<?php

namespace Tests\Feature\Realtime;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderStatusChanged;
use App\Domain\Pricing\Enums\VehicleCategory;
use App\Domain\Pricing\Models\PricingRuleVersion;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderBroadcastTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_creating_an_order_broadcasts_a_status_change_to_searching_provider(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $this->seedActiveVersion();
        $user = $this->authenticateNewCustomer('+966503330040');
        $token = $this->tokenFor($user);

        $vehicleId = $this->withToken($token)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $this->withToken($token)->postJson('/api/v1/customers/me/orders', [
            'vehicle_id' => $vehicleId,
            'service_type' => ServiceCapability::StandardFlatbed->value,
            'vehicle_category' => VehicleCategory::Sedan->value,
            'pickup_latitude' => 24.7136,
            'pickup_longitude' => 46.6753,
            'pickup_formatted_address' => 'King Fahd Road, Riyadh',
            'dropoff_latitude' => 24.6408,
            'dropoff_longitude' => 46.7728,
            'dropoff_formatted_address' => 'Al Malaz, Riyadh',
        ])->assertCreated();

        Event::assertDispatched(OrderStatusChanged::class, function (OrderStatusChanged $event) {
            return $event->from === OrderStatus::Pending
                && $event->to === OrderStatus::SearchingProvider;
        });
    }

    public function test_cancelling_an_order_broadcasts_the_transition_on_its_own_channel(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $this->seedActiveVersion();
        $user = $this->authenticateNewCustomer('+966503330041');
        $token = $this->tokenFor($user);

        $vehicleId = $this->withToken($token)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $orderId = $this->withToken($token)->postJson('/api/v1/customers/me/orders', [
            'vehicle_id' => $vehicleId,
            'service_type' => ServiceCapability::StandardFlatbed->value,
            'vehicle_category' => VehicleCategory::Sedan->value,
            'pickup_latitude' => 24.7136,
            'pickup_longitude' => 46.6753,
            'pickup_formatted_address' => 'King Fahd Road, Riyadh',
            'dropoff_latitude' => 24.6408,
            'dropoff_longitude' => 46.7728,
            'dropoff_formatted_address' => 'Al Malaz, Riyadh',
        ])->json('data.order.id');

        $this->withToken($token)->postJson("/api/v1/customers/me/orders/{$orderId}/cancel")->assertOk();

        Event::assertDispatched(OrderStatusChanged::class, function (OrderStatusChanged $event) use ($orderId) {
            if ($event->to !== OrderStatus::CancelledByCustomer) {
                return false;
            }

            $channels = $event->broadcastOn();

            return $channels[0]->name === "private-orders.{$orderId}";
        });
    }
}
