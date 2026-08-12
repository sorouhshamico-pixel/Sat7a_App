<?php

namespace Tests\Feature\Realtime;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
use App\Domain\Fleet\Enums\ServiceCapability;
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

class ChannelAuthorizationTest extends TestCase
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

    private function createOrderAsCustomer(User $customer): string
    {
        $token = $this->tokenFor($customer);

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

    private function authorize(string $token, string $channelName): TestResponse
    {
        // The test suite defaults BROADCAST_CONNECTION to `null` (see
        // phpunit.xml) so setup steps in these tests (order/driver
        // creation, which trigger real ShouldBroadcast events) never try
        // to reach a real Reverb server over the network. The null/log
        // broadcasters don't implement real channel-authorization logic at
        // all though (their auth() is a no-op), so exercising the actual
        // callbacks in routes/channels.php needs the real
        // Pusher-protocol-compatible driver Reverb uses — no network call
        // is made for *this*, channel auth is pure local HMAC signing.
        // Switched here, right before the auth call, rather than for the
        // whole test: routes/channels.php was already `require`d once at
        // application boot against the (still-default, null) connection —
        // Broadcast::channel() registers each pattern onto whichever
        // driver instance is current *at registration time* — so a fresh
        // `reverb` driver instance has no channels registered until this
        // re-requires it.
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');

        return $this->actingAsToken('POST', $token, '/api/v1/broadcasting/auth', [
            'channel_name' => 'private-'.$channelName,
            'socket_id' => '1234.5678',
        ]);
    }

    public function test_the_owning_customer_can_authorize_their_orders_channel(): void
    {
        $this->seedActiveVersion();
        $customer = $this->authenticateNewCustomer('+966503330050');
        $orderId = $this->createOrderAsCustomer($customer);

        $response = $this->authorize($this->tokenFor($customer), "orders.{$orderId}");

        $response->assertOk();
        $this->assertArrayHasKey('auth', $response->json());
    }

    public function test_a_different_customer_cannot_authorize_someone_elses_orders_channel(): void
    {
        $this->seedActiveVersion();
        $owner = $this->authenticateNewCustomer('+966503330051');
        $orderId = $this->createOrderAsCustomer($owner);

        $stranger = $this->authenticateNewCustomer('+966503330052');

        $response = $this->authorize($this->tokenFor($stranger), "orders.{$orderId}");

        $response->assertStatus(403);
    }

    public function test_platform_staff_with_orders_view_all_can_authorize_any_orders_channel(): void
    {
        $this->seedActiveVersion();
        $customer = $this->authenticateNewCustomer('+966503330053');
        $orderId = $this->createOrderAsCustomer($customer);

        $dispatcher = User::factory()->admin()->create();
        $dispatcher->roles()->attach(Role::where('name', RoleName::Dispatcher->value)->firstOrFail());

        $response = $this->authorize($this->tokenFor($dispatcher), "orders.{$orderId}");

        $response->assertOk();
    }

    public function test_platform_staff_without_orders_view_all_cannot_authorize_an_orders_channel(): void
    {
        $this->seedActiveVersion();
        $customer = $this->authenticateNewCustomer('+966503330054');
        $orderId = $this->createOrderAsCustomer($customer);

        $financeOfficer = User::factory()->admin()->create();
        $financeOfficer->roles()->attach(Role::where('name', RoleName::FinanceOfficer->value)->firstOrFail());

        $response = $this->authorize($this->tokenFor($financeOfficer), "orders.{$orderId}");

        $response->assertStatus(403);
    }

    public function test_a_nonexistent_orders_channel_is_denied(): void
    {
        $this->seedActiveVersion();
        $customer = $this->authenticateNewCustomer('+966503330055');

        $response = $this->authorize($this->tokenFor($customer), 'orders.not-a-real-order');

        $response->assertStatus(403);
    }

    public function test_an_unauthenticated_request_cannot_authorize_any_channel(): void
    {
        $response = $this->postJson('/api/v1/broadcasting/auth', [
            'channel_name' => 'private-orders.whatever',
            'socket_id' => '1234.5678',
        ]);

        $response->assertStatus(401);
    }

    public function test_a_driver_can_authorize_their_own_drivers_channel_but_not_another_drivers(): void
    {
        $this->postJson('/api/v1/providers/register', [
            'business_name' => 'شركة النقل السريع',
            'owner_name' => 'محمد أحمد',
            'contact_phone' => '+966501110050',
        ])->assertCreated();

        $provider = Provider::query()->where('contact_phone', '+966501110050')->firstOrFail();
        $provider->status = ProviderStatus::Approved;
        $provider->save();

        $ownerToken = $this->tokenFor($provider->owner);

        $driverAId = $this->actingAsToken('POST', $ownerToken, '/api/v1/providers/me/drivers', [
            'name' => 'سالم القحطاني', 'phone' => '+966502220050',
        ])->json('data.driver.id');
        $driverBId = $this->actingAsToken('POST', $ownerToken, '/api/v1/providers/me/drivers', [
            'name' => 'فهد العتيبي', 'phone' => '+966502220051',
        ])->json('data.driver.id');

        $driverAUser = $provider->drivers()->where('public_id', $driverAId)->firstOrFail()->user;
        $driverBUser = $provider->drivers()->where('public_id', $driverBId)->firstOrFail()->user;

        $ownResponse = $this->authorize($this->tokenFor($driverAUser), "drivers.{$driverAId}");
        $ownResponse->assertOk();

        $otherResponse = $this->authorize($this->tokenFor($driverBUser), "drivers.{$driverAId}");
        $otherResponse->assertStatus(403);
    }
}
