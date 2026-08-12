<?php

namespace App\Domain\Authentication\Actions;

use App\Domain\Authentication\Models\OtpCode;
use App\Domain\Notifications\Contracts\SmsProvider;
use App\Domain\Users\Enums\UserType;
use Illuminate\Support\Facades\Hash;

/**
 * Issues a phone OTP challenge. Send-frequency is capped by the
 * `otp-send` named rate limiter on the route (see docs/SECURITY.md
 * §Rate limiting) — this action only handles generation/storage/dispatch.
 */
class SendOtpAction
{
    public function __construct(private readonly SmsProvider $smsProvider) {}

    public function handle(string $phone, UserType $userType, ?string $requestIp): OtpCode
    {
        // Invalidate any still-pending OTPs for this phone/type so an old
        // code can never be replayed once a new one is requested.
        OtpCode::query()
            ->where('phone', $phone)
            ->where('user_type', $userType->value)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $plainCode = (string) random_int(100000, 999999);

        $otp = OtpCode::create([
            'phone' => $phone,
            'user_type' => $userType->value,
            'code_hash' => Hash::make($plainCode),
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(5),
            'requested_ip' => $requestIp,
        ]);

        $this->smsProvider->send(
            $phone,
            __('auth.otp_message', ['code' => $plainCode]),
        );

        return $otp;
    }
}
