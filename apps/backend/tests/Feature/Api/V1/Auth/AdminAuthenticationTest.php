<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Domain\Authentication\Services\TwoFactorAuthenticationService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Laravel's `sanctum` guard (a RequestGuard) caches the resolved user
     * for the lifetime of the test's app container, so a test that
     * authenticates as different tokens in sequence must force a fresh
     * guard resolution between requests — otherwise it silently keeps
     * "logged in" as whichever identity was resolved first, which never
     * happens in real usage (each real request gets a fresh container).
     *
     * @param  array<string, mixed>  $data
     */
    private function postAsToken(string $token, string $uri, array $data = []): TestResponse
    {
        Auth::forgetGuards();

        return $this->withToken($token)->postJson($uri, $data);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('errors.0.code', 'INVALID_CREDENTIALS');
    }

    public function test_login_rejects_unknown_email_with_same_error_as_wrong_password(): void
    {
        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('errors.0.code', 'INVALID_CREDENTIALS');
    }

    public function test_login_without_mfa_enabled_requires_mfa_setup(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.stage', 'mfa_setup_required');
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_full_mfa_setup_and_login_challenge_flow(): void
    {
        $user = User::factory()->admin()->create(['email' => 'admin@example.com']);

        $setupToken = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('data.token');

        $setupResponse = $this->postAsToken($setupToken, '/api/v1/auth/admin/mfa/setup');
        $setupResponse->assertOk();
        $secret = $setupResponse->json('data.secret');

        $google2fa = app(Google2FA::class);
        $validCode = $google2fa->getCurrentOtp($secret);

        $confirmResponse = $this->postAsToken($setupToken, '/api/v1/auth/admin/mfa/confirm', ['code' => $validCode]);
        $confirmResponse->assertOk();
        $this->assertCount(8, $confirmResponse->json('data.recovery_codes'));
        $fullToken = $confirmResponse->json('data.token');
        $this->assertNotEmpty($fullToken);

        $user->refresh();
        $this->assertTrue($user->hasMfaEnabled());

        // The setup token must no longer work after the full token is issued.
        $this->postAsToken($setupToken, '/api/v1/auth/admin/mfa/setup')
            ->assertStatus(401);

        // Subsequent login now requires the MFA challenge, not setup again.
        $challengeToken = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('data.token');

        $newValidCode = $google2fa->getCurrentOtp($user->two_factor_secret);

        $challengeResponse = $this->postAsToken($challengeToken, '/api/v1/auth/admin/mfa/challenge', ['code' => $newValidCode]);

        $challengeResponse->assertOk();
        $this->assertNotEmpty($challengeResponse->json('data.token'));
    }

    public function test_mfa_challenge_rejects_wrong_code(): void
    {
        $user = User::factory()->admin()->create(['email' => 'admin@example.com']);
        app(TwoFactorAuthenticationService::class)->startSetup($user);
        $user->refresh();
        $google2fa = app(Google2FA::class);
        app(TwoFactorAuthenticationService::class)->confirmSetup($user, $google2fa->getCurrentOtp($user->two_factor_secret));

        $challengeToken = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('data.token');

        $response = $this->withToken($challengeToken)
            ->postJson('/api/v1/auth/admin/mfa/challenge', ['code' => '000000']);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'MFA_INVALID_CODE');
    }

    public function test_a_bare_password_never_yields_a_fully_privileged_token(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $loginResponse = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.token');

        // A setup-scoped token must not grant access to an unrelated
        // authenticated endpoint (sessions listing).
        $this->withToken($token)
            ->getJson('/api/v1/auth/sessions')
            ->assertStatus(403);
    }
}
