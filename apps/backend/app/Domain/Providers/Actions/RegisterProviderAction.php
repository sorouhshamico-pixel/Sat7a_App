<?php

namespace App\Domain\Providers\Actions;

use App\Domain\Authentication\Actions\SendOtpAction;
use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
use App\Domain\Providers\Enums\ProviderStatus;
use App\Domain\Providers\Models\Provider;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Provider registration and phone verification are two separate steps that
 * share the existing OTP endpoints: this action creates the (unverified)
 * provider_staff user, the Provider record, and the provider_owner role
 * assignment in one transaction, then sends an OTP. The applicant completes
 * authentication via the existing POST /api/v1/auth/otp/verify — which
 * already knows how to authenticate an *existing* provider_staff user (see
 * docs/SECURITY.md §Authentication) — no changes to Phase 1 auth were
 * needed. A provider is never auto-activated: it's created with
 * status=pending and only a compliance action moves it forward (see
 * docs/COMPLIANCE.md).
 */
class RegisterProviderAction
{
    public function __construct(private readonly SendOtpAction $sendOtpAction) {}

    /**
     * @param  array{business_name: string, owner_name: string, contact_phone: string, contact_email: ?string, commercial_registration_number: ?string, tax_number: ?string}  $data
     */
    public function handle(array $data, ?string $requestIp): Provider
    {
        $provider = DB::transaction(function () use ($data) {
            $owner = User::create([
                'name' => $data['owner_name'],
                'phone' => $data['contact_phone'],
                'user_type' => UserType::ProviderStaff->value,
                'status' => UserStatus::Active->value,
            ]);

            $ownerRole = Role::query()->where('name', RoleName::ProviderOwner->value)->firstOrFail();
            $owner->roles()->attach($ownerRole->id);

            $provider = new Provider([
                'business_name' => $data['business_name'],
                'commercial_registration_number' => $data['commercial_registration_number'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'contact_phone' => $data['contact_phone'],
                'contact_email' => $data['contact_email'] ?? null,
                'status' => ProviderStatus::Pending->value,
            ]);
            $provider->owner_id = $owner->id;
            $provider->save();

            $owner->forceFill(['provider_id' => $provider->id])->save();

            return $provider;
        });

        $this->sendOtpAction->handle(
            phone: $data['contact_phone'],
            userType: UserType::ProviderStaff,
            requestIp: $requestIp,
        );

        return $provider;
    }
}
