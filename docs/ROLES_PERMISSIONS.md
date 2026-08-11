# Roles & Permissions

## Status

Design document for Phase 2 (Roles & Permissions), which implements this against the database
and Policies. Phase 0 only records the model so later phases build against an agreed shape.

## Model

Roles and permissions are separate concepts (RBAC with fine-grained permissions), not a single
role enum checked ad hoc in controllers. A user has one or more roles; a role bundles
permissions; authorization checks in code always go through Laravel Policies/Gates against
permissions, never a raw `$user->role === 'admin'` check, and never rely on hiding a UI button
as the actual control (the backend re-checks every time — see `docs/SECURITY.md`).

## Roles (initial set)

```text
Customer
Provider Owner
Fleet Manager
Driver
Dispatcher
Customer Support
Finance Officer
Compliance Officer
Operations Manager
Admin
Super Admin
```

## Example permissions

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
```

The exact permission catalog is finalized and enforced in Phase 2, and extended as each later
phase introduces new protected actions (e.g. dispatch overrides in Phase 9, settlement approval
in Phase 14).

## Rules

- Every sensitive admin action (provider approval/suspension, document verification, pricing
  changes, refunds, settlements, manual dispatch assignment, admin order cancellation, role/
  permission changes, bank account changes, user suspension) is checked against a permission
  **and** written to the audit log (see `docs/SECURITY.md`).
- No admin action should rely only on a hidden UI element — the API re-validates permission on
  every request.
- Admin impersonation is not implemented unless a clear need arises; if it ever is, it requires
  MFA, an explicit permission, a visible banner during the session, full audit logging, and a
  block on payment-affecting actions while impersonating.
