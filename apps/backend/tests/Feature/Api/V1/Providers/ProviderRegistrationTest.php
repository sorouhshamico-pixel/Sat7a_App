<?php

namespace Tests\Feature\Api\V1\Providers;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Providers\Enums\ProviderStatus;
use App\Domain\Providers\Models\Provider;
use App\Domain\Users\Enums\UserType;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProviderRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_registration_creates_a_pending_provider_with_an_owner_and_sends_otp(): void
    {
        $response = $this->postJson('/api/v1/providers/register', [
            'business_name' => 'شركة النقل السريع',
            'owner_name' => 'محمد أحمد',
            'contact_phone' => '+966501112233',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.provider.status', ProviderStatus::Pending->value);

        $provider = Provider::query()->firstOrFail();
        $this->assertSame('+966501112233', $provider->contact_phone);
        $this->assertTrue($provider->owner->hasRole(RoleName::ProviderOwner->value));
        $this->assertSame(UserType::ProviderStaff, $provider->owner->user_type);

        $this->assertDatabaseHas('otp_codes', [
            'phone' => '+966501112233',
            'user_type' => UserType::ProviderStaff->value,
        ]);
    }

    public function test_registration_then_otp_verify_completes_authentication(): void
    {
        $this->postJson('/api/v1/providers/register', [
            'business_name' => 'شركة النقل السريع',
            'owner_name' => 'محمد أحمد',
            'contact_phone' => '+966501112233',
        ])->assertCreated();

        $otp = OtpCode::query()->where('phone', '+966501112233')->firstOrFail();
        $otp->update(['code_hash' => Hash::make('123456')]);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+966501112233',
            'code' => '123456',
            'user_type' => UserType::ProviderStaff->value,
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_registration_rejects_a_phone_already_in_use(): void
    {
        $this->postJson('/api/v1/providers/register', [
            'business_name' => 'شركة النقل السريع',
            'owner_name' => 'محمد أحمد',
            'contact_phone' => '+966501112233',
        ])->assertCreated();

        $response = $this->postJson('/api/v1/providers/register', [
            'business_name' => 'شركة أخرى',
            'owner_name' => 'شخص آخر',
            'contact_phone' => '+966501112233',
        ]);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_request_cannot_view_provider_profile(): void
    {
        $this->getJson('/api/v1/providers/me')->assertStatus(401);
    }

    public function test_owner_can_view_and_update_their_own_provider_profile(): void
    {
        $this->postJson('/api/v1/providers/register', [
            'business_name' => 'شركة النقل السريع',
            'owner_name' => 'محمد أحمد',
            'contact_phone' => '+966501112233',
        ])->assertCreated();

        $provider = Provider::query()->firstOrFail();
        $token = $provider->owner->createToken('test', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/providers/me')
            ->assertOk()
            ->assertJsonPath('data.provider.business_name', 'شركة النقل السريع');

        $update = $this->withToken($token)->patchJson('/api/v1/providers/me', [
            'business_name' => 'اسم جديد للشركة',
        ]);

        $update->assertOk();
        $update->assertJsonPath('data.provider.business_name', 'اسم جديد للشركة');
    }
}
