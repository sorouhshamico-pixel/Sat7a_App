<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+966501234567';

    public function test_customer_can_request_and_verify_otp_and_is_auto_provisioned(): void
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => self::PHONE,
            'user_type' => UserType::Customer->value,
        ])->assertOk();

        $otp = OtpCode::query()->where('phone', self::PHONE)->firstOrFail();
        $plainCode = $this->extractPlainCode($otp);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => $plainCode,
            'user_type' => UserType::Customer->value,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.user.user_type', UserType::Customer->value);
        $this->assertNotEmpty($response->json('data.token'));

        $this->assertDatabaseHas('users', [
            'phone' => self::PHONE,
            'user_type' => UserType::Customer->value,
        ]);
    }

    public function test_verify_rejects_incorrect_code(): void
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => self::PHONE,
            'user_type' => UserType::Customer->value,
        ])->assertOk();

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => '000000',
            'user_type' => UserType::Customer->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'OTP_INVALID');
    }

    public function test_verify_rejects_expired_code(): void
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => self::PHONE,
            'user_type' => UserType::Customer->value,
        ])->assertOk();

        $otp = OtpCode::query()->where('phone', self::PHONE)->firstOrFail();
        $plainCode = $this->extractPlainCode($otp);
        $otp->update(['expires_at' => now()->subMinute()]);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => $plainCode,
            'user_type' => UserType::Customer->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'OTP_EXPIRED');
    }

    public function test_verify_locks_out_after_max_attempts(): void
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => self::PHONE,
            'user_type' => UserType::Customer->value,
        ])->assertOk();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', [
                'phone' => self::PHONE,
                'code' => '000000',
                'user_type' => UserType::Customer->value,
            ]);
        }

        $otp = OtpCode::query()->where('phone', self::PHONE)->firstOrFail();
        $plainCode = $this->extractPlainCode($otp);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => $plainCode,
            'user_type' => UserType::Customer->value,
        ]);

        $response->assertStatus(429);
        $response->assertJsonPath('errors.0.code', 'OTP_MAX_ATTEMPTS_EXCEEDED');
    }

    public function test_provider_staff_is_never_auto_provisioned_by_otp_login(): void
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => self::PHONE,
            'user_type' => UserType::ProviderStaff->value,
        ])->assertOk();

        $otp = OtpCode::query()->where('phone', self::PHONE)->firstOrFail();
        $plainCode = $this->extractPlainCode($otp);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => $plainCode,
            'user_type' => UserType::ProviderStaff->value,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('users', ['phone' => self::PHONE]);
    }

    public function test_provider_staff_with_provisioned_account_can_authenticate(): void
    {
        User::factory()->providerStaff()->create(['phone' => self::PHONE]);

        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => self::PHONE,
            'user_type' => UserType::ProviderStaff->value,
        ])->assertOk();

        $otp = OtpCode::query()->where('phone', self::PHONE)->firstOrFail();
        $plainCode = $this->extractPlainCode($otp);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => $plainCode,
            'user_type' => UserType::ProviderStaff->value,
        ]);

        $response->assertOk();
    }

    public function test_suspended_account_cannot_authenticate(): void
    {
        User::factory()->providerStaff()->suspended()->create(['phone' => self::PHONE]);

        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => self::PHONE,
            'user_type' => UserType::ProviderStaff->value,
        ])->assertOk();

        $otp = OtpCode::query()->where('phone', self::PHONE)->firstOrFail();
        $plainCode = $this->extractPlainCode($otp);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => $plainCode,
            'user_type' => UserType::ProviderStaff->value,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'ACCOUNT_SUSPENDED');
    }

    /**
     * The plain OTP is never persisted — tests recover it the same way the
     * SMS provider would have received it, via the log adapter's payload,
     * by brute-forcing against the stored hash is not viable, so instead we
     * regenerate through a controlled seam: re-derive from the log channel.
     * Simpler and just as valid for this test: patch the hash directly.
     */
    private function extractPlainCode(OtpCode $otp): string
    {
        $plainCode = '123456';
        $otp->update(['code_hash' => Hash::make($plainCode)]);

        return $plainCode;
    }
}
