# Roadmap

The platform is built in independently shippable phases. Each phase is gated by: tests passing,
lint passing, static analysis passing, frontend typecheck/build passing, a security-impact
review, and a documentation update — before moving to the next phase (see the Definition of
Done below). Nothing is skipped or bypassed to "make a phase pass."

## Status

| Phase | Name | Status |
|---|---|---|
| 0 | Architecture & Foundation | Done |
| 1 | Authentication & Security Foundation | Done |
| 2 | Roles & Permissions | Done |
| 3 | Provider Onboarding | **In progress** |
| 4 | Fleet & Drivers | Not started |
| 5 | Customer Profiles & Vehicles | Not started |
| 6 | Maps & Location Foundation | Not started |
| 7 | Pricing Engine | Not started |
| 8 | Orders | Not started |
| 9 | Dispatch & Matching | Not started |
| 10 | Realtime | Not started |
| 11 | Live Location Tracking | Not started |
| 12 | Payments | Not started |
| 13 | Financial Ledger & Commission | Not started |
| 14 | Settlements | Not started |
| 15 | Reviews & Disputes | Not started |
| 16 | Notifications | Not started |
| 17 | Operations Command Center | Not started |
| 18 | Finance & Compliance Admin | Not started |
| 19 | Customer Web App Polish | Not started |
| 20 | Provider Web / PWA | Not started |
| 21 | PWA | Not started |
| 22 | Marketing & SEO | Not started |
| 23 | Security Hardening | Not started |
| 24 | Performance & Load Hardening | Not started |
| 25 | Production Readiness | Not started |

## Phase 0 — Architecture & Foundation (this phase)

Scope: monorepo skeleton, Laravel backend, Next.js frontend, PostgreSQL + PostGIS, Redis,
environment configuration, CI foundation, documentation, coding standards, health endpoints,
base test infrastructure.

Definition of done for Phase 0 specifically:
- Backend runs (`php artisan serve`).
- Frontend runs (`npm run dev`) and builds (`npm run build`).
- PostgreSQL connection works; PostGIS extension enabled.
- Redis works (cache/session/queue/broadcasting all point at it).
- Backend tests run (`php artisan test` / Pint / Larastan).
- Frontend tests run (Vitest, typecheck, ESLint).
- CI pipeline passes on push.
- Documentation created (this set of files).
- No secrets committed (`.env` is gitignored, `.env.example` has no real values).

Status: done, except PostGIS extension activation, which needs a one-time admin-elevated step
on this Windows dev machine (`infrastructure/install-postgis-windows.ps1`, documented in
`docs/DEPLOYMENT.md`) that hasn't been run yet. Nothing built so far depends on PostGIS —
it's needed starting Phase 6.

## Phase 1 — Authentication & Security Foundation (this phase)

Implemented: customer/provider-staff phone + OTP login (`app/Domain/Authentication/`), admin
email + password + mandatory TOTP MFA with recovery codes, session/device management (list,
revoke one, revoke all), the `/api/v1` response envelope wired into a global exception renderer
(`app/Exceptions/ApiExceptionRenderer.php`) so every error — validation, auth, not-found, rate
limit, unexpected — comes back in the standard shape with a stable `ErrorCode`, baseline
security headers, and named rate limiters for OTP send/verify and admin login (see
`docs/SECURITY.md` and `docs/API_SPECIFICATION.md` for details).

Not yet in this phase: roles/permissions enforcement (that's Phase 2), provider-staff account
provisioning (Phase 3/4 — for now, provider-staff OTP login only works for accounts seeded
directly, since there's no onboarding flow yet).

Testing note: the test suite runs against a real local PostgreSQL database
(`tow_platform_testing`), not SQLite, because migrations use Postgres-specific syntax (CHECK
constraints now, PostGIS geography columns from Phase 6 on) that SQLite can't run. CI
provisions the same database as a service container.

## Phase 2 — Roles & Permissions (this phase)

Implemented: `roles`/`permissions`/`permission_role`/`role_user` tables, seeded via
`database/seeders/RolePermissionSeeder.php`, a `Gate::before` hook
(`app/Providers/AuthorizationServiceProvider.php`) that turns every permission slug into a
usable Laravel authorization ability, admin role-management endpoints (list roles, assign/revoke
a user's role) gated by the `roles.manage` permission, an immutable audit log
(`App\Domain\Audit\Services\AuditLogger`) wired into role assignment/revocation, and an
`admin:create-super-admin` artisan command to bootstrap the first admin account (there's no
public admin registration endpoint). See `docs/ROLES_PERMISSIONS.md` for the full catalog.

Not yet in this phase: provider-scoped role enforcement (a fleet manager restricted to *their*
provider) — that needs the Provider domain from Phase 3/4; Policy classes for domain models,
since no domain models with ownership exist yet beyond User/Role/Permission themselves.

## Phase 3 — Provider Onboarding (this phase)

Implemented: `providers` and (polymorphic) `documents` tables; provider registration
(`POST /api/v1/providers/register`) that creates the provider_staff owner user, the pending
`Provider` row, and the `provider_owner` role assignment in one transaction, then sends an OTP —
the applicant completes authentication via the existing Phase 1 OTP-verify endpoint, so
Phase 1 auth code needed zero changes. Provider profile view/update
(`GET`/`PATCH /api/v1/providers/me`); private document upload/list/download
(`/api/v1/providers/me/documents`, `/api/v1/documents/{document}/download`) with content-based
MIME validation, size limits, and double-extension rejection. Admin compliance endpoints under
`/api/v1/admin/providers` and `/api/v1/admin/documents` (list/filter, approve, reject, suspend,
verify, reject), every mutating action gated by a permission and audited. A daily scheduled
command (`compliance:check-document-expiry`) alerts on documents expiring in 30/15/7/1 days or
already expired. See `docs/COMPLIANCE.md` for details.

Not yet in this phase: fleet/drivers (Phase 4, which will also let a provider_staff user who
isn't the owner — a fleet manager or driver — resolve "their" provider); bank accounts
(Phase 14); real notification delivery for expiry alerts (Phase 16, logged for now).

## Definition of Done for every phase

1. Feature implemented.
2. Tests added.
3. Existing tests pass.
4. Lint passes.
5. Static analysis passes (Larastan/PHPStan).
6. TypeScript passes.
7. Frontend tests pass.
8. Production build passes.
9. Security impact reviewed.
10. Documentation updated.
11. Git diff reviewed.
12. Commit created with a Conventional Commit message.
13. Push completed if a remote exists and credentials permit it.
14. Working tree clean.

## Explicitly deferred (not built until a proven need exists)

Driver bidding, dynamic pricing, wallet, subscriptions, corporate accounts, insurance
integrations, intercity transport, loyalty, promo codes, referrals, call masking, AI support,
ML-based pricing, Kubernetes, Kafka, microservices, full event sourcing, complex CQRS,
blockchain. Architectural hooks are left for the ones that are cheap to leave open (feature
flags, city/service-zone modeling instead of hardcoded "Riyadh", currency as a code rather than
a hardcoded "SAR" string) — see `docs/ARCHITECTURE.md` §10.

## Future Saudi expansion & mobile

Geographic modeling uses `cities` / `service_zones` rather than hardcoding Riyadh in domain
logic, so Jeddah, Dammam, Makkah, Madinah, and Taif can be added later without a rewrite. All
business logic lives server-side in the Laravel API so Flutter Customer and Provider/Driver
apps can be built later against the same `/api/v1` surface without backend rework (see
`docs/API_SPECIFICATION.md` and `docs/ARCHITECTURE.md`).
