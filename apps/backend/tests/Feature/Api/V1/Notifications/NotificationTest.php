<?php

namespace Tests\Feature\Api\V1\Notifications;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Notifications\Models\Notification;
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

class NotificationTest extends TestCase
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

    private function createOrder(string $customerPhone): string
    {
        $customer = $this->authenticateNewCustomer($customerPhone);
        $customerToken = $this->tokenFor($customer);

        $vehicleId = $this->actingAsToken('POST', $customerToken, '/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        return $this->actingAsToken('POST', $customerToken, '/api/v1/customers/me/orders', [
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
    }

    public function test_creating_an_order_notifies_the_customer(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110180', '+966502220180');
        $orderId = $this->createOrder('+966503330180');

        $customer = User::query()->where('phone', '+966503330180')->firstOrFail();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'type' => 'order_created',
        ]);

        $notification = Notification::query()->where('user_id', $customer->id)->where('type', 'order_created')->firstOrFail();
        $this->assertSame($orderId, $notification->data['order_id']);
        $this->assertContains('sms', $notification->channels);
        $this->assertContains('push', $notification->channels);
        $this->assertContains('email', $notification->channels);
        $this->assertNotContains('whatsapp', $notification->channels);
    }

    public function test_key_trip_milestones_notify_the_customer_but_not_every_status(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110181', '+966502220181');
        $orderId = $this->createOrder('+966503330181');
        $customer = User::query()->where('phone', '+966503330181')->firstOrFail();

        $offerId = $this->actingAsToken('GET', $this->driverToken, '/api/v1/drivers/me/dispatch-offers')
            ->json('data.offers.0.id');
        $this->actingAsToken('POST', $this->driverToken, "/api/v1/drivers/me/dispatch-offers/{$offerId}/accept")->assertOk();

        // provider_assigned happens as part of acceptance.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'type' => 'order_status_updated',
        ]);

        foreach (['provider_en_route', 'provider_arrived', 'vehicle_loading', 'trip_started', 'in_transit', 'vehicle_delivered', 'completed'] as $status) {
            $this->actingAsToken('POST', $this->driverToken, "/api/v1/drivers/me/orders/{$orderId}/status", ['status' => $status])
                ->assertOk();
        }

        // Milestones (provider_assigned, provider_en_route, provider_arrived, completed) = 4 notifications.
        // vehicle_loading/trip_started/in_transit/vehicle_delivered are not milestones.
        $milestoneCount = Notification::query()
            ->where('user_id', $customer->id)
            ->where('type', 'order_status_updated')
            ->count();
        $this->assertSame(4, $milestoneCount);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $customer->id,
            'type' => 'order_status_updated',
            'data->status' => 'vehicle_loading',
        ]);
    }

    public function test_cancelling_an_order_notifies_the_customer_and_the_assigned_driver(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110182', '+966502220182');
        $orderId = $this->createOrder('+966503330182');
        $customer = User::query()->where('phone', '+966503330182')->firstOrFail();
        $customerToken = $this->tokenFor($customer);

        $offerId = $this->actingAsToken('GET', $this->driverToken, '/api/v1/drivers/me/dispatch-offers')
            ->json('data.offers.0.id');
        $this->actingAsToken('POST', $this->driverToken, "/api/v1/drivers/me/dispatch-offers/{$offerId}/accept")->assertOk();

        $this->actingAsToken('POST', $customerToken, "/api/v1/customers/me/orders/{$orderId}/cancel", [
            'reason' => 'غيرت رأيي',
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'type' => 'order_cancelled',
        ]);

        $driverUser = User::query()->where('phone', '+966502220182')->firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $driverUser->id,
            'type' => 'order_cancelled',
        ]);
    }

    public function test_a_customer_can_view_and_mark_read_their_own_notifications(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110183', '+966502220183');
        $this->createOrder('+966503330183');
        $customer = User::query()->where('phone', '+966503330183')->firstOrFail();
        $token = $this->tokenFor($customer);

        $response = $this->actingAsToken('GET', $token, '/api/v1/notifications/me')->assertOk();
        $this->assertCount(1, $response->json('data.notifications'));
        $this->assertSame(1, $response->json('meta.unread_count'));

        $notificationId = $response->json('data.notifications.0.id');

        $this->actingAsToken('POST', $token, "/api/v1/notifications/me/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('data.notification.read_at', fn ($value) => $value !== null);

        $after = $this->actingAsToken('GET', $token, '/api/v1/notifications/me')->assertOk();
        $this->assertSame(0, $after->json('meta.unread_count'));
    }

    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110184', '+966502220184');
        $this->createOrder('+966503330184');
        $customer = User::query()->where('phone', '+966503330184')->firstOrFail();

        $notificationId = Notification::query()->where('user_id', $customer->id)->firstOrFail()->public_id;

        $otherCustomer = $this->authenticateNewCustomer('+966503330185');
        $this->actingAsToken('POST', $this->tokenFor($otherCustomer), "/api/v1/notifications/me/{$notificationId}/read")
            ->assertStatus(404);
    }

    public function test_disabling_a_channel_excludes_it_from_the_notification(): void
    {
        $this->seedActiveVersion();
        $this->registerApprovedProviderWithAvailableTruck('+966501110186', '+966502220186');

        $customer = $this->authenticateNewCustomer('+966503330186');
        $this->actingAsToken('PATCH', $this->tokenFor($customer), '/api/v1/customers/me', [
            'notification_preferences' => ['sms' => false, 'push' => true, 'email' => true, 'whatsapp' => false],
        ])->assertOk();

        $vehicleId = $this->actingAsToken('POST', $this->tokenFor($customer), '/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $this->actingAsToken('POST', $this->tokenFor($customer), '/api/v1/customers/me/orders', [
            'vehicle_id' => $vehicleId,
            'service_type' => ServiceCapability::StandardFlatbed->value,
            'vehicle_category' => VehicleCategory::Sedan->value,
            'pickup_latitude' => self::PICKUP_LAT,
            'pickup_longitude' => self::PICKUP_LNG,
            'pickup_formatted_address' => 'King Fahd Road, Riyadh',
            'dropoff_latitude' => 24.6408,
            'dropoff_longitude' => 46.7728,
            'dropoff_formatted_address' => 'Al Malaz, Riyadh',
        ])->assertCreated();

        $notification = Notification::query()->where('user_id', $customer->id)->where('type', 'order_created')->firstOrFail();
        $this->assertNotContains('sms', $notification->channels);
        $this->assertContains('push', $notification->channels);
        $this->assertContains('email', $notification->channels);
    }
}
