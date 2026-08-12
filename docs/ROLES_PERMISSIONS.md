# Roles & Permissions

## Status

Implemented, Phase 2. `roles`, `permissions`, `permission_role`, `role_user` tables
(`database/migrations/2026_08_12_072827_*` through `..._072830_*`), seeded by
`database/seeders/RolePermissionSeeder.php`. Customer-facing ownership checks (a customer only
ever seeing their own orders, etc.) are a separate, later concern per domain (Orders, Payments,
...) — not part of this RBAC layer, since customers don't have roles.

## Model

Roles and permissions are separate concepts (RBAC with fine-grained permissions), not a single
role enum checked ad hoc in controllers. A user has zero or more roles (`role_user`); a role
bundles permissions (`permission_role`). Authorization checks always go through Laravel's
Gate/Policy layer — never a raw `$user->user_type === 'admin_staff'` check — and the backend
re-validates on every request; hiding a button in the frontend is never the actual control (see
`docs/SECURITY.md`).

Every permission slug in `App\Domain\Authorization\Enums\PermissionName` doubles as a Laravel
authorization ability via a single `Gate::before` hook
(`App\Providers\AuthorizationServiceProvider`), so `$user->can('orders.view_all')`,
`Gate::authorize(...)`, and the `can:` route middleware all work directly against the permission
catalog without a manual `Gate::define()` per permission.

## Roles

```text
Customer            — not part of RBAC; ownership-based access only
Provider Owner       (provider_owner, scope: provider)
Fleet Manager        (fleet_manager, scope: provider)
Driver               (driver, scope: provider)
Dispatcher            (dispatcher, scope: platform)
Customer Support       (customer_support, scope: platform)
Finance Officer         (finance_officer, scope: platform)
Compliance Officer       (compliance_officer, scope: platform)
Operations Manager        (operations_manager, scope: platform)
Admin                      (admin, scope: platform)
Super Admin                 (super_admin, scope: platform)
```

Provider-scoped roles (`provider_owner`, `fleet_manager`, `driver`) are seeded now so the
catalog is stable, but real provider-scoped enforcement (a fleet manager can only manage *their*
provider's fleet) lands with the Provider domain in Phase 3/4 — `role_user` doesn't carry a
`provider_id` yet; a provider-staff user's provider affiliation will live on the future
`providers`/`drivers` tables instead, so a permission check becomes "does this role grant the
permission" **and** "does this user belong to the provider this resource belongs to" (a separate,
ownership-style check, same pattern as customer ownership).

## Permission catalog (`App\Domain\Authorization\Enums\PermissionName`)

```text
orders.view              orders.view_all          orders.assign
orders.cancel             orders.refund

providers.view            providers.approve         providers.suspend

drivers.manage

fleet.manage

payments.view              payments.refund

settlements.view           settlements.approve

documents.view              documents.view_sensitive documents.verify

pricing.view                 pricing.update

users.view                    users.suspend

audit.view

roles.manage
```

Additive only — grows as later phases introduce new protected actions (e.g. dispatch overrides
in Phase 9, settlement approval in Phase 14). An existing permission's meaning never changes
underneath an already-seeded role; a semantic change is a new permission.

## Role → permission mapping

See `database/seeders/RolePermissionSeeder.php` for the authoritative mapping. Notably:
`roles.manage` is granted **only** to `super_admin`, deliberately excluded even from `admin`, to
avoid a privilege-escalation loop (an `admin` account should never be able to grant itself or
anyone else `super_admin`).

## Admin role management (implemented)

`GET /api/v1/admin/roles`, `GET/POST/DELETE /api/v1/admin/users/{user}/roles[/{role}]` — all
gated by the `roles.manage` permission and requiring a fully-privileged token (see
`docs/API_SPECIFICATION.md`). The very first `super_admin` account is created out-of-band via
`php artisan admin:create-super-admin {email} {name}` — there is no public admin registration
endpoint.

## Audit logging (implemented)

`App\Domain\Audit\Services\AuditLogger` writes an immutable `audit_logs` row
(`App\Domain\Audit\Models\AuditLog`) for every role assignment/revocation
(`App\Domain\Authorization\Actions\AssignRoleAction` / `RevokeRoleAction`), recording actor,
action, entity, old/new values, IP, and user agent. The same logger is reused as later phases
add their own audited actions (provider approval/suspension, document verification, pricing
changes, refunds, settlements, manual dispatch assignment, admin order cancellation, bank
account changes, user suspension — see `docs/SECURITY.md` §Audited actions). Rows are never
updated or deleted by application code.

## Rules

- Every sensitive admin action is checked against a permission **and** written to the audit log.
- No admin action relies only on a hidden UI element — the API re-validates permission on every
  request.
- Admin impersonation is not implemented unless a clear need arises; if it ever is, it requires
  MFA, an explicit permission, a visible banner during the session, full audit logging, and a
  block on payment-affecting actions while impersonating.
