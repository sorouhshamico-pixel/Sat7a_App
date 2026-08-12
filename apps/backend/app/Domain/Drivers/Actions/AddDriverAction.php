<?php

namespace App\Domain\Drivers\Actions;

use App\Domain\Authentication\Actions\SendOtpAction;
use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
use App\Domain\Drivers\Enums\DriverStatus;
use App\Domain\Drivers\Models\Driver;
use App\Domain\Providers\Models\Provider;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Same shape as provider registration (Phase 3): creates the driver's
 * (unverified) provider_staff login alongside the Driver profile in one
 * transaction, then sends an OTP so the driver completes authentication
 * via the existing POST /api/v1/auth/otp/verify. A driver is never
 * self-registered — only an owner/fleet manager with `drivers.manage` can
 * add one to their own provider (see docs/ROLES_PERMISSIONS.md).
 */
class AddDriverAction
{
    public function __construct(private readonly SendOtpAction $sendOtpAction) {}

    /**
     * @param  array{name: string, phone: string, nationality: ?string, license_number: ?string, license_expires_at: ?string}  $data
     */
    public function handle(Provider $provider, array $data, ?string $requestIp): Driver
    {
        $driver = DB::transaction(function () use ($provider, $data) {
            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'user_type' => UserType::ProviderStaff->value,
                'status' => UserStatus::Active->value,
                'provider_id' => $provider->id,
            ]);

            $driverRole = Role::query()->where('name', RoleName::Driver->value)->firstOrFail();
            $user->roles()->attach($driverRole->id);

            $driver = new Driver([
                'nationality' => $data['nationality'] ?? null,
                'license_number' => $data['license_number'] ?? null,
                'license_expires_at' => $data['license_expires_at'] ?? null,
                'status' => DriverStatus::Active->value,
                'is_available' => false,
            ]);
            $driver->provider_id = $provider->id;
            $driver->user_id = $user->id;
            $driver->save();

            return $driver;
        });

        $this->sendOtpAction->handle(
            phone: $data['phone'],
            userType: UserType::ProviderStaff,
            requestIp: $requestIp,
        );

        return $driver;
    }
}
