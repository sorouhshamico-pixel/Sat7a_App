<?php

namespace App\Domain\Authorization\Enums;

/**
 * Fine-grained permission catalog (see docs/ROLES_PERMISSIONS.md). Grows as
 * later phases introduce new protected actions — additive only, an existing
 * permission's meaning never changes underneath an already-seeded role.
 */
enum PermissionName: string
{
    case OrdersView = 'orders.view';
    case OrdersViewAll = 'orders.view_all';
    case OrdersAssign = 'orders.assign';
    case OrdersCancel = 'orders.cancel';
    case OrdersRefund = 'orders.refund';

    case ProvidersView = 'providers.view';
    case ProvidersApprove = 'providers.approve';
    case ProvidersSuspend = 'providers.suspend';

    // Provider-side self-service only (own provider — see
    // App\Http\Controllers\Concerns\ResolvesProvider). Deliberately
    // distinct from *Suspend below: a provider_owner/fleet_manager must
    // never be able to reach the platform-wide admin suspend endpoints
    // just because they can manage their own fleet/drivers.
    case DriversManage = 'drivers.manage';
    case FleetManage = 'fleet.manage';

    // Platform/compliance-side only — suspending any provider's driver or
    // tow truck, not scoped to "your own provider" (see
    // App\Domain\Drivers\Actions\SuspendDriverAction and
    // App\Domain\Fleet\Actions\SuspendTowTruckAction).
    case DriversSuspend = 'drivers.suspend';
    case FleetSuspend = 'fleet.suspend';

    case PaymentsView = 'payments.view';
    case PaymentsRefund = 'payments.refund';

    case SettlementsView = 'settlements.view';
    case SettlementsApprove = 'settlements.approve';

    // A provider's own bank details are "highly sensitive" (see
    // docs/SECURITY.md §Data classification) — `settlements.view` alone
    // only ever shows a masked IBAN to platform staff; this permission is
    // required to see the unmasked value.
    case SettlementsViewBankDetails = 'settlements.view_bank_details';

    case DocumentsView = 'documents.view';
    case DocumentsViewSensitive = 'documents.view_sensitive';
    case DocumentsVerify = 'documents.verify';

    // Disputes are a distinct sensitive workflow — customer_support and
    // operations_manager only (see docs/REVIEWS_DISPUTES.md). Reviews have
    // no dedicated permission of their own: admin/compliance visibility
    // into a provider's reviews reuses `providers.view`, since it's
    // read-only information *about* a provider, not a separate workflow.
    case DisputesView = 'disputes.view';
    case DisputesManage = 'disputes.manage';

    case PricingView = 'pricing.view';
    case PricingUpdate = 'pricing.update';

    case UsersView = 'users.view';
    case UsersSuspend = 'users.suspend';

    case AuditView = 'audit.view';

    // Managing role assignments is itself a permission-gated, audited
    // action — see docs/SECURITY.md §Audit. Restricted to super_admin in
    // the seeded catalog to avoid a privilege-escalation loop.
    case RolesManage = 'roles.manage';
}
