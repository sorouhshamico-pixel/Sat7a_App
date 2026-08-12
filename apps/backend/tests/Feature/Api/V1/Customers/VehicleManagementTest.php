<?php

namespace Tests\Feature\Api\V1\Customers;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

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

    public function test_customer_can_add_list_update_and_delete_a_vehicle(): void
    {
        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $create = $this->withToken($token)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2020,
            'type' => 'sedan',
            'color' => 'white',
        ]);
        $create->assertCreated();
        $vehicleId = $create->json('data.vehicle.id');

        $index = $this->withToken($token)->getJson('/api/v1/customers/me/vehicles');
        $index->assertOk();
        $this->assertCount(1, $index->json('data.vehicles'));

        $update = $this->withToken($token)->patchJson("/api/v1/customers/me/vehicles/{$vehicleId}", [
            'color' => 'black',
        ]);
        $update->assertOk();
        $update->assertJsonPath('data.vehicle.color', 'black');

        $delete = $this->withToken($token)->deleteJson("/api/v1/customers/me/vehicles/{$vehicleId}");
        $delete->assertOk();

        $this->assertDatabaseMissing('vehicles', ['public_id' => $vehicleId]);
    }

    public function test_a_customer_cannot_update_another_customers_vehicle(): void
    {
        $userA = $this->authenticateNewCustomer('+966501234567');
        $tokenA = $this->tokenFor($userA);

        $vehicleId = $this->withToken($tokenA)->postJson('/api/v1/customers/me/vehicles', [
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2020,
        ])->json('data.vehicle.id');

        $userB = $this->authenticateNewCustomer('+966501234599');
        $tokenB = $this->tokenFor($userB);

        $response = $this->actingAsToken('PATCH', $tokenB, "/api/v1/customers/me/vehicles/{$vehicleId}", [
            'color' => 'red',
        ]);

        $response->assertStatus(404);
    }
}
