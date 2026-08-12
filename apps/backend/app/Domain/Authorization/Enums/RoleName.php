<?php

namespace App\Domain\Authorization\Enums;

/**
 * The role catalog from docs/ROLES_PERMISSIONS.md. Customers are not part
 * of RBAC — they only ever act on resources they own, which is an
 * ownership check, not a permission check.
 */
enum RoleName: string
{
    // Provider-side roles (scope: provider) — assigned to `provider_staff`
    // users once the Provider domain exists (Phase 3/4). The role/permission
    // catalog is seeded now so onboarding doesn't need a schema change.
    case ProviderOwner = 'provider_owner';
    case FleetManager = 'fleet_manager';
    case Driver = 'driver';

    // Platform-side roles (scope: platform) — assigned to `admin_staff` users.
    case Dispatcher = 'dispatcher';
    case CustomerSupport = 'customer_support';
    case FinanceOfficer = 'finance_officer';
    case ComplianceOfficer = 'compliance_officer';
    case OperationsManager = 'operations_manager';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function scope(): string
    {
        return match ($this) {
            self::ProviderOwner, self::FleetManager, self::Driver => 'provider',
            default => 'platform',
        };
    }
}
