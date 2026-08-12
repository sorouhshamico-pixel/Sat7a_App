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

    case DriversManage = 'drivers.manage';

    case FleetManage = 'fleet.manage';

    case PaymentsView = 'payments.view';
    case PaymentsRefund = 'payments.refund';

    case SettlementsView = 'settlements.view';
    case SettlementsApprove = 'settlements.approve';

    case DocumentsView = 'documents.view';
    case DocumentsViewSensitive = 'documents.view_sensitive';
    case DocumentsVerify = 'documents.verify';

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
