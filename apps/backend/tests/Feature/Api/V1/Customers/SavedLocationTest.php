<?php

namespace Tests\Feature\Api\V1\Customers;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SavedLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
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

    public function test_customer_can_save_a_home_location(): void
    {
        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $response = $this->withToken($token)->postJson('/api/v1/customers/me/locations', [
            'label' => 'home',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'formatted_address' => 'حي النرجس، الرياض',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.location.label', 'home');
    }

    public function test_customer_cannot_save_a_second_home_location(): void
    {
        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/v1/customers/me/locations', [
            'label' => 'home',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'formatted_address' => 'حي النرجس، الرياض',
        ])->assertCreated();

        $response = $this->withToken($token)->postJson('/api/v1/customers/me/locations', [
            'label' => 'home',
            'latitude' => 24.8,
            'longitude' => 46.7,
            'formatted_address' => 'حي آخر، الرياض',
        ]);

        $response->assertStatus(422);
    }

    public function test_customer_can_save_multiple_custom_locations(): void
    {
        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/v1/customers/me/locations', [
            'label' => 'custom',
            'custom_label' => 'Gym',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'formatted_address' => 'حي النرجس، الرياض',
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/customers/me/locations', [
            'label' => 'custom',
            'custom_label' => 'Clinic',
            'latitude' => 24.72,
            'longitude' => 46.68,
            'formatted_address' => 'حي الملقا، الرياض',
        ])->assertCreated();

        $index = $this->withToken($token)->getJson('/api/v1/customers/me/locations');
        $index->assertOk();
        $this->assertCount(2, $index->json('data.locations'));
    }

    public function test_customer_can_update_and_delete_a_saved_location(): void
    {
        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $locationId = $this->withToken($token)->postJson('/api/v1/customers/me/locations', [
            'label' => 'work',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'formatted_address' => 'حي العليا، الرياض',
        ])->json('data.location.id');

        $update = $this->withToken($token)->patchJson("/api/v1/customers/me/locations/{$locationId}", [
            'formatted_address' => 'حي العليا، برج المملكة، الرياض',
        ]);
        $update->assertOk();
        $update->assertJsonPath('data.location.formatted_address', 'حي العليا، برج المملكة، الرياض');

        $this->withToken($token)->deleteJson("/api/v1/customers/me/locations/{$locationId}")->assertOk();
        $this->assertDatabaseMissing('saved_locations', ['public_id' => $locationId]);
    }
}
