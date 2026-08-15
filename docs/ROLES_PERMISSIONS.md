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

Provider-scoped enforcement is implemented as of Phase 4: `users.provider_id` is the single
source of truth for which provider an owner/fleet-manager/driver belongs to, and every
provider-facing endpoint (`/api/v1/providers/me/...`) resolves through it
(`App\Http\Controllers\Concerns\ResolvesProvider`) rather than taking a `{provider}` route
parameter — so a permission check ("does this role grant `drivers.manage`") is never by itself
sufficient to reach another provider's data; there's structurally no route through which to try.

## Provider-side vs. platform-side permissions with the same name shape

`drivers.manage`/`fleet.manage` (provider self-service, own provider only) are **deliberately
separate** from `drivers.suspend`/`fleet.suspend` (platform/compliance-side, any provider) —
they are not the same permission reused at two scopes. Sharing one permission across both was
the original design and turned out to be a real bug (caught in Phase 4, before shipping): since
a `Gate::before`-driven permission check has no concept of "which provider," a `provider_owner`
who legitimately has `drivers.manage` for their own fleet would also have passed the middleware
guarding the platform-wide admin suspend endpoint for *any* provider's driver. The fix is
structural, not a patch: two distinct permissions, granted to disjoint role sets.

## Permission catalog (`App\Domain\Authorization\Enums\PermissionName`)

```text
orders.view              orders.view_all          orders.assign
orders.cancel             orders.refund

providers.view            providers.approve         providers.suspend

drivers.manage             drivers.suspend

fleet.manage                fleet.suspend

payments.view              payments.refund

settlements.view           settlements.approve       settlements.view_bank_details

documents.view              documents.view_sensitive documents.verify

disputes.view                disputes.manage

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

## Fleet & drivers (implemented, Phase 4)

Provider self-service (`/api/v1/providers/me/drivers`, `/api/v1/providers/me/fleet/...`) is
gated by `drivers.manage`/`fleet.manage`, granted to `provider_owner` and `fleet_manager` (not
`driver`) — always scoped to the caller's own provider by construction, never by a route
parameter. Platform-side suspension (`/api/v1/admin/drivers/{driver}/suspend`,
`/api/v1/admin/tow-trucks/{towTruck}/suspend`) is gated by the separate
`drivers.suspend`/`fleet.suspend` permissions, granted to `compliance_officer` and
`operations_manager` — both audited via `App\Domain\Drivers\Actions\SuspendDriverAction` /
`App\Domain\Fleet\Actions\SuspendTowTruckAction`.

## Pricing (implemented, Phase 7)

`pricing.view` (list rate-card versions) is granted to `provider_owner`, `finance_officer`, and
`operations_manager` — broad visibility, since pricing affects everyone's business. `pricing.update`
(create/activate a version) is granted **only** to `admin`/`super_admin` — a financially
critical, platform-wide change stays tightly held, unlike view access. Both actions the update
permission gates are audited via `App\Domain\Audit\Services\AuditLogger`.

## Orders (implemented, Phase 8)

`orders.view_all` (list/view any order) and `orders.cancel` (platform-side cancellation of any
order) were seeded back in Phase 2 and are now actually enforced on `/api/v1/admin/orders/...`
(see `docs/ORDER_LIFECYCLE.md`) — granted to `dispatcher`/`customer_support`/`operations_manager`
per the existing catalog, with only `operations_manager` (plus `admin`/`super_admin`) also
holding `orders.cancel`, so a dispatcher or support agent can see orders but not cancel them.
Customer-side order access (a customer only ever seeing their own orders) is not part of this
RBAC layer at all — customers have no roles — it's enforced structurally by
`App\Http\Controllers\Concerns\ResolvesCustomer` plus scoping every lookup through the caller's
own `orders()` relation, the same pattern already used for vehicles/saved-locations.

## Dispatch (implemented, Phase 9)

`orders.assign` — seeded back in Phase 2, granted to `dispatcher` and `operations_manager` — now
gates the manual dispatch fallback (`POST /api/v1/admin/orders/{order}/dispatch/retry` and
`.../dispatch/assign`, see `docs/DISPATCH_ENGINE.md`). No new permission was needed. Driver-facing
dispatch-offer endpoints (`/api/v1/drivers/me/dispatch-offers/...`) are ownership-scoped like
customer orders — a driver reaches only their own offers via
`App\Http\Controllers\Concerns\ResolvesDriver`, not a permission check, since there is no
platform-wide "view any driver's offers" business need today.

## Payments & ledger (implemented, Phase 12/13)

`payments.view`/`payments.refund` (Phase 12) and `settlements.view` (Phase 13) — all seeded back
in Phase 2, granted to `finance_officer` — now gate `/api/v1/admin/payments/...` and
`/api/v1/admin/providers/{provider}/balance`/`.../ledger` respectively. No new permissions were
needed for either phase; `settlements.view` was seeded ahead of its own implementation, matching
the same "seed the full catalog early, enforce it as each phase lands" approach `orders.assign`
followed in Phase 9. Provider-facing ledger/balance endpoints
(`/api/v1/providers/me/balance`/`.../ledger`) are ownership-scoped like every other `/me`
endpoint — no permission gate, since a provider only ever sees their own.

## Settlements (implemented, Phase 14)

The entire settlement-batch lifecycle — generate, then every status transition (submit, approve,
process, paid, fail, cancel) — reuses `settlements.approve` end to end, rather than minting a new
permission per action. This is the same choice already made for the dispatch-override workflow in
Phase 9 (`orders.assign` reused for both retry and manual-assign): a single permission for a
whole admin workflow, not one per step. `settlements.view` continues to gate read-only listing
(`GET /api/v1/admin/settlements`, `.../settlements/{settlement}`), unchanged from Phase 13.

`settlements.view_bank_details` is new — a provider's bank account is "highly sensitive" data
(`docs/SECURITY.md` §Data classification); `settlements.view`/`settlements.approve` alone only
ever surface a masked IBAN (`App\Http\Resources\Api\V1\ProviderBankAccountResource`), the same
"general access vs. an extra permission for the sensitive field" split already established by
`documents.view_sensitive` in Phase 6. Seeded to `finance_officer` alongside the other settlement
permissions; `admin`/`super_admin` inherit it automatically via their "all permissions" filters.
Provider-facing bank-account endpoints (`/api/v1/providers/me/bank-account`) are ownership-scoped
like every other `/me` endpoint, and the owning provider always sees their own unmasked IBAN
regardless of this permission.

## Reviews & Disputes (implemented, Phase 15)

Reviews mint no new permission: `GET /api/v1/providers/me/reviews` is ownership-scoped like every
other `/me` endpoint, and `GET /api/v1/admin/providers/{provider}/reviews` reuses `providers.view`
— read-only information about a provider, not a distinct workflow.

Disputes get two new permissions — `disputes.view` (list/view any dispute) and `disputes.manage`
(advance a dispute through its whole `open`→`under_review`→`resolved`/`rejected` lifecycle, one
permission for the whole workflow, the same choice already made for `orders.assign` in Phase 9
and `settlements.approve` in Phase 14) — both seeded to `customer_support` and
`operations_manager` per `docs/PRODUCT_REQUIREMENTS.md` ("Customer Support — handles tickets and
disputes"). Unlike reviews, this is a genuinely new sensitive workflow rather than read access to
an existing resource, which is why it got its own permissions instead of reusing one.

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
