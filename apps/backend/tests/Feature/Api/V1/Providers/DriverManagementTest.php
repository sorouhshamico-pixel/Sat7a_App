<?php

namespace Tests\Feature\Api\V1\Providers;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Providers\Models\Provider;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriverManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function registerProvider(): Provider
    {
        $this->postJson('/api/v1/providers/register', [
            'business_name' => 'شركة النقل السريع',
            'owner_name' => 'محمد أحمد',
            'contact_phone' => '+966501112233',
        ])->assertCreated();

        return Provider::query()->firstOrFail();
    }

    public function test_owner_can_add_a_driver_and_otp_login_completes_afterward(): void
    {
        $provider = $this->registerProvider();
        $token = $this->tokenFor($provider->owner);

        $response = $this->withToken($token)->postJson('/api/v1/providers/me/drivers', [
            'name' => 'سالم القحطاني',
            'phone' => '+966502223344',
            'license_number' => 'LIC-9911',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.driver.status', 'active');

        $this->assertDatabaseHas('drivers', ['provider_id' => $provider->id]);

        $driverUser = User::query()->where('phone', '+966502223344')->firstOrFail();
        $this->assertTrue($driverUser->hasRole(RoleName::Driver->value));
        $this->assertSame($provider->id, $driverUser->provider_id);

        $otp = OtpCode::query()->where('phone', '+966502223344')->firstOrFail();
        $otp->update(['code_hash' => Hash::make('123456')]);

        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+966502223344',
            'code' => '123456',
            'user_type' => UserType::ProviderStaff->value,
        ]);
        $verify->assertOk();
    }

    public function test_adding_a_driver_rejects_a_phone_already_in_use(): void
    {
        $provider = $this->registerProvider();
        $token = $this->tokenFor($provider->owner);

        $this->withToken($token)->postJson('/api/v1/providers/me/drivers', [
            'name' => 'سالم القحطاني',
            'phone' => '+966502223344',
        ])->assertCreated();

        $response = $this->withToken($token)->postJson('/api/v1/providers/me/drivers', [
            'name' => 'شخص آخر',
            'phone' => '+966502223344',
        ]);

        $response->assertStatus(422);
    }

    public function test_a_driver_cannot_add_other_drivers(): void
    {
        $provider = $this->registerProvider();
        $ownerToken = $this->tokenFor($provider->owner);

        $this->withToken($ownerToken)->postJson('/api/v1/providers/me/drivers', [
            'name' => 'سالم القحطاني',
            'phone' => '+966502223344',
        ])->assertCreated();

        $driverUser = User::query()->where('phone', '+966502223344')->firstOrFail();
        $driverToken = $this->tokenFor($driverUser);

        $response = $this->actingAsToken('POST', $driverToken, '/api/v1/providers/me/drivers', [
            'name' => 'شخص آخر',
            'phone' => '+966502223355',
        ]);

        $response->assertStatus(403);
    }

    public function test_owner_can_list_drivers_and_toggle_availability(): void
    {
        $provider = $this->registerProvider();
        $token = $this->tokenFor($provider->owner);

        $addResponse = $this->withToken($token)->postJson('/api/v1/providers/me/drivers', [
            'name' => 'سالم القحطاني',
            'phone' => '+966502223344',
        ]);
        $driverId = $addResponse->json('data.driver.id');

        $list = $this->withToken($token)->getJson('/api/v1/providers/me/drivers');
        $list->assertOk();
        $this->assertCount(1, $list->json('data.drivers'));

        $update = $this->withToken($token)->patchJson("/api/v1/providers/me/drivers/{$driverId}/availability", [
            'is_available' => true,
        ]);
        $update->assertOk();
        $update->assertJsonPath('data.driver.is_available', true);
    }
}
