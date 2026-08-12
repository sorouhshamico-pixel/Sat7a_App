<?php

namespace Tests\Feature\Api\V1\Customers;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Customers\Models\Customer;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
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

    public function test_customer_profile_is_auto_provisioned_on_first_otp_login(): void
    {
        $user = $this->authenticateNewCustomer();

        $this->assertDatabaseHas('customers', ['user_id' => $user->id]);

        $customer = Customer::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(
            Customer::defaultNotificationPreferences(),
            $customer->notification_preferences,
        );
    }

    public function test_unauthenticated_request_cannot_view_profile(): void
    {
        $this->getJson('/api/v1/customers/me')->assertStatus(401);
    }

    public function test_customer_can_view_and_update_their_profile(): void
    {
        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $show = $this->withToken($token)->getJson('/api/v1/customers/me');
        $show->assertOk();
        $show->assertJsonPath('data.customer.phone', $user->phone);

        $update = $this->withToken($token)->patchJson('/api/v1/customers/me', [
            'name' => 'فهد العتيبي',
            'locale' => 'en',
            'notification_preferences' => ['sms' => false],
        ]);

        $update->assertOk();
        $update->assertJsonPath('data.customer.name', 'فهد العتيبي');
        $update->assertJsonPath('data.customer.locale', 'en');
        $update->assertJsonPath('data.customer.notification_preferences.sms', false);

        $this->assertSame('فهد العتيبي', $user->fresh()->name);
    }

    public function test_customer_can_upload_an_avatar(): void
    {
        $user = $this->authenticateNewCustomer();
        $token = $this->tokenFor($user);

        $response = $this->withToken($token)->post('/api/v1/customers/me/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('data.customer.avatar_url'));

        $customer = Customer::query()->where('user_id', $user->id)->firstOrFail();
        Storage::disk('public')->assertExists($customer->avatar_path);
    }
}
