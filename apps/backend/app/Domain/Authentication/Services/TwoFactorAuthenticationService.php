<?php

namespace App\Domain\Authentication\Services;

use App\Domain\Authentication\Exceptions\AdminAuthenticationException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238) enrollment/verification for admin/platform-staff accounts.
 * MFA is mandatory for this account type (see docs/SECURITY.md
 * §Authentication) — there is no "disable MFA" path exposed here by design.
 */
class TwoFactorAuthenticationService
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * @return array{secret: string, otpauth_url: string}
     */
    public function startSetup(User $user): array
    {
        if ($user->hasMfaEnabled()) {
            throw AdminAuthenticationException::mfaAlreadyEnabled();
        }

        $secret = $this->google2fa->generateSecretKey();

        $user->forceFill(['two_factor_secret' => $secret])->save();

        $otpauthUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        return ['secret' => $secret, 'otpauth_url' => $otpauthUrl];
    }

    /**
     * @return list<string> plain recovery codes — shown to the user exactly
     *                      once; only their hashes are persisted.
     */
    public function confirmSetup(User $user, string $code): array
    {
        if ($user->hasMfaEnabled()) {
            throw AdminAuthenticationException::mfaAlreadyEnabled();
        }

        if ($user->two_factor_secret === null || ! $this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            throw AdminAuthenticationException::mfaInvalidCode();
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(
                fn (string $recoveryCode) => Hash::make($recoveryCode),
                $recoveryCodes,
            ),
        ])->save();

        return $recoveryCodes;
    }

    public function verifyChallenge(User $user, string $code): bool
    {
        if (! $user->hasMfaEnabled() || $user->two_factor_secret === null) {
            throw AdminAuthenticationException::mfaNotEnabled();
        }

        if ($this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            return true;
        }

        return $this->consumeRecoveryCodeIfValid($user, $code);
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => Str::upper(Str::random(4).'-'.Str::random(4)))
            ->all();
    }

    private function consumeRecoveryCodeIfValid(User $user, string $code): bool
    {
        /** @var list<string> $hashedCodes */
        $hashedCodes = $user->two_factor_recovery_codes ?? [];

        foreach ($hashedCodes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                unset($hashedCodes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashedCodes)])->save();

                return true;
            }
        }

        return false;
    }
}
