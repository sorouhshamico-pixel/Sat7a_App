<?php

namespace App\Domain\Users\Enums;

/**
 * Broad account category, which determines the authentication mechanism
 * (see docs/SECURITY.md §Authentication). Fine-grained roles/permissions
 * within provider staff and admin staff are layered on top in Phase 2
 * (see docs/ROLES_PERMISSIONS.md) — this enum only distinguishes "how does
 * this account log in and what's its broad shape."
 */
enum UserType: string
{
    case Customer = 'customer';

    // Provider-side staff: Provider Owner, Fleet Manager, Driver — all
    // authenticate via phone + OTP, scoped to a provider (Phase 3/4).
    case ProviderStaff = 'provider_staff';

    // Platform-side staff: Dispatcher, Customer Support, Finance Officer,
    // Compliance Officer, Operations Manager, Admin, Super Admin — all
    // authenticate via email + password + mandatory MFA.
    case AdminStaff = 'admin_staff';

    public function usesOtpAuthentication(): bool
    {
        return match ($this) {
            self::Customer, self::ProviderStaff => true,
            self::AdminStaff => false,
        };
    }

    public function usesPasswordAuthentication(): bool
    {
        return $this === self::AdminStaff;
    }
}
