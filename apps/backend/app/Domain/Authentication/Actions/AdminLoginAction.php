<?php

namespace App\Domain\Authentication\Actions;

use App\Domain\Authentication\Exceptions\AdminAuthenticationException;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Admin/platform-staff login is always two steps: this action only checks
 * the password and issues a short-lived, narrowly-scoped token for
 * whichever second step is required next. It never returns a fully
 * privileged access token — MFA is mandatory for admin accounts (see
 * docs/SECURITY.md §Authentication), so a bare password is never enough.
 */
class AdminLoginAction
{
    /**
     * @return array{stage: 'mfa_setup_required'|'mfa_challenge_required', token: string}
     */
    public function handle(string $email, string $password): array
    {
        $user = User::query()
            ->where('email', $email)
            ->where('user_type', UserType::AdminStaff->value)
            ->first();

        if ($user === null || $user->password === null || ! Hash::check($password, $user->password)) {
            throw AdminAuthenticationException::invalidCredentials();
        }

        if ($user->status === UserStatus::Suspended) {
            throw AdminAuthenticationException::accountSuspended();
        }

        if (! $user->hasMfaEnabled()) {
            $token = $user->createToken('mfa-setup', ['mfa-setup'], now()->addMinutes(15));

            return ['stage' => 'mfa_setup_required', 'token' => $token->plainTextToken];
        }

        $token = $user->createToken('mfa-challenge', ['mfa-challenge'], now()->addMinutes(5));

        return ['stage' => 'mfa_challenge_required', 'token' => $token->plainTextToken];
    }
}
