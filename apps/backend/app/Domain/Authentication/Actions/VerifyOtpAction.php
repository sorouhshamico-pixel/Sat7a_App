<?php

namespace App\Domain\Authentication\Actions;

use App\Domain\Authentication\Exceptions\OtpVerificationException;
use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Customers\Models\Customer;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Verifies an OTP and authenticates (or, for customers only, provisions)
 * the account. Provider-side staff are never auto-created here — those
 * accounts are provisioned by their provider owner or an admin (Phase 3/4);
 * an unrecognized phone attempting provider-staff login is a hard failure,
 * not a silent signup, to avoid impersonation (see docs/SECURITY.md).
 */
class VerifyOtpAction
{
    /**
     * @return array{user: User, token: string}
     */
    public function handle(string $phone, string $plainCode, UserType $userType): array
    {
        $otp = OtpCode::query()
            ->where('phone', $phone)
            ->where('user_type', $userType->value)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($otp === null) {
            throw OtpVerificationException::invalidCode();
        }

        if ($otp->isExpired()) {
            throw OtpVerificationException::expired();
        }

        if ($otp->hasExceededMaxAttempts()) {
            throw OtpVerificationException::maxAttemptsExceeded();
        }

        if (! Hash::check($plainCode, $otp->code_hash)) {
            $otp->increment('attempts');

            throw OtpVerificationException::invalidCode();
        }

        return DB::transaction(function () use ($otp, $phone, $userType) {
            $otp->update(['consumed_at' => now()]);

            $user = User::query()
                ->where('phone', $phone)
                ->where('user_type', $userType->value)
                ->first();

            if ($user === null) {
                if ($userType !== UserType::Customer) {
                    throw OtpVerificationException::accountNotProvisioned();
                }

                $user = User::create([
                    'phone' => $phone,
                    'user_type' => UserType::Customer->value,
                    'status' => UserStatus::Active->value,
                ]);

                $customer = new Customer([
                    'notification_preferences' => Customer::defaultNotificationPreferences(),
                ]);
                $customer->user_id = $user->id;
                $customer->save();
            }

            if ($user->status === UserStatus::Suspended) {
                throw OtpVerificationException::accountSuspended();
            }

            if ($user->phone_verified_at === null) {
                $user->forceFill(['phone_verified_at' => now()])->save();
            }

            $token = $user->createToken(
                name: 'otp-login',
                abilities: ['*'],
                expiresAt: now()->addDays(30),
            );

            return ['user' => $user, 'token' => $token->plainTextToken];
        });
    }
}
