<?php

namespace Tests\Feature\Console;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Orders\Models\Order;
use App\Domain\Pricing\Enums\VehicleCategory;
use App\Domain\Pricing\Models\PricingRuleVersion;
use App\Domain\Tracking\Models\OrderLocationPing;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PurgeExpiredDataCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_purges_otp_codes_past_the_configured_retention_window_but_keeps_recent_ones(): void
    {
        config(['retention.otp_codes_hours' => 24]);

        $old = OtpCode::create([
            'phone' => '+966501112221',
            'user_type' => UserType::Customer,
            'code_hash' => Hash::make('123456'),
            'max_attempts' => 5,
            'expires_at' => now()->subHours(48),
        ]);

        $recent = OtpCode::create([
            'phone' => '+966501112222',
            'user_type' => UserType::Customer,
            'code_hash' => Hash::make('654321'),
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(5),
        ]);

        Artisan::call('data:purge-expired');

        $this->assertDatabaseMissing('otp_codes', ['id' => $old->id]);
        $this->assertDatabaseHas('otp_codes', ['id' => $recent->id]);
    }

    public function test_purges_location_pings_past_the_configured_retention_window_but_keeps_recent_ones(): void
    {
        config(['retention.location_pings_days' => 90]);

        $orderId = $this->createOrderAsNewCustomer('+966501112233');
        $order = Order::query()->where('public_id', $orderId)->firstOrFail();

        $old = OrderLocationPing::create([
            'order_id' => $order->id,
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'recorded_at' => now()->subDays(120),
        ]);

        $recent = OrderLocationPing::create([
            'order_id' => $order->id,
            'latitude' => 24.7140,
            'longitude' => 46.6758,
            'recorded_at' => now()->subDays(1),
        ]);

        Artisan::call('data:purge-expired');

        $this->assertDatabaseMissing('order_location_pings', ['id' => $old->id]);
        $this->assertDatabaseHas('order_location_pings', ['id' => $recent->id]);
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

    private function createOrderAsNewCustomer(string $phone): string
    {
        $this->seedActiveVersion();

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

        $user = User::query()->where('phone', $phone)->firstOrFail();
        $token = $this->tokenFor($user);

        $vehicleId = $this->actingAsToken('POST', $token, '/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        return $this->actingAsToken('POST', $token, '/api/v1/customers/me/orders', [
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
    }
}
