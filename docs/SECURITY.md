# Security

## Status

Phase 1: authentication, OTP, sessions, and admin MFA are implemented (see below) — everything
else in this document past that section is still design/Phase 0 baseline. A dedicated Phase 23
security-hardening audit happens before production readiness. This document is updated as each
control is implemented — it is not a promise of controls that don't exist yet.

## Threat model reference (OWASP)

Design and review against OWASP ASVS and the OWASP API Security Top 10. At minimum, every phase
that touches user input, authorization, or money is reviewed against: IDOR, broken object/
function-level authorization, mass assignment, SQL injection, XSS, CSRF, SSRF, command
injection, path traversal, brute force, credential stuffing, OTP abuse, rate abuse, file upload
attacks, webhook replay, session fixation, sensitive data exposure, improper CORS, open
redirects.

## Secrets

- No API keys, passwords, payment keys, or tokens in code or Git history, ever.
- `.env` is gitignored; `.env.example` contains variable names only, no real values (see
  `apps/backend/.env.example`).
- When an external API integration doesn't yet have real credentials, the code path uses an
  interface + fake/mock adapter (e.g. `PAYMENT_GATEWAY_DRIVER=fake`, `SMS_PROVIDER_DRIVER=log`)
  rather than inventing placeholder-looking real keys.

## Data classification

- **Public**: marketing content, service type catalog, published ratings aggregate.
- **Internal**: operational metrics, non-PII logs.
- **Confidential**: customer/provider PII, order details, documents.
- **Highly sensitive**: OTPs, auth tokens, bank account numbers, payment metadata, compliance
  documents' contents.

Access to confidential/highly-sensitive data requires an explicit permission (e.g.
`documents.view_sensitive`, `customers.view_sensitive`) — not implied by role alone (see
`docs/ROLES_PERMISSIONS.md`).

## Authentication (implemented, Phase 1)

- Customer & Provider-staff: mobile number + OTP (`App\Domain\Authentication\Actions\SendOtpAction`,
  `VerifyOtpAction`). Provider-staff accounts are never auto-created by OTP login — only
  customers are; provider-staff must already exist (provisioned in Phase 3/4), otherwise login
  fails with `NOT_FOUND` rather than silently registering an unverified account.
- Admin: email + password + **mandatory** TOTP MFA + recovery codes
  (`App\Domain\Authentication\Actions\AdminLoginAction`,
  `App\Domain\Authentication\Services\TwoFactorAuthenticationService`). A bare password never
  yields a fully-privileged token — login always returns a narrowly-scoped, short-lived Sanctum
  token (ability `mfa-setup` or `mfa-challenge` only) that can *only* reach the corresponding MFA
  endpoint; every other authenticated endpoint requires the `*` ability, which those tokens don't
  have (see `routes/api.php`).
- Audit logging of admin actions lands in Phase 2 alongside RBAC — not yet implemented.

## OTP handling (implemented, Phase 1)

Hashed at rest via `Hash::make()` (never plain text), 5-minute expiration, capped at 5 verify
attempts (`otp_codes.attempts`/`max_attempts`), send-rate-limited to 5/hour per phone + 20/hour
per IP (`otp-send` named limiter), verify-rate-limited per phone+IP (`otp-verify` named limiter),
one-time use (`consumed_at`), any still-pending OTP for a phone is invalidated the moment a new
one is requested, never written to logs (the SMS adapter logs the *message text*, in
`SMS_PROVIDER_DRIVER=log` dev mode only — the code itself is never separately logged).

## Sessions (implemented, Phase 1)

`App\Http\Controllers\Api\V1\Auth\SessionController`: a user can list their active
sessions (Sanctum tokens = devices/logins) with the current one flagged, revoke a specific
session, or revoke all others. A user can only ever act on their own tokens — there is no
cross-user session lookup. "Rotate session identifiers on authentication" doesn't apply the way
it would to cookie sessions: every OTP/MFA success issues a brand-new token rather than reusing
one.

## Rate limiting baseline (tunable, implemented per-endpoint as each lands)

| Endpoint class | Limit |
|---|---|
| OTP send | 5 / hour |
| OTP verify | 5 attempts |
| Login | 10 / 15 min |
| Order create | 5 / 10 min |
| Public estimate | rate limited (exact figure set in Phase 6/8) |
| Admin login | strict, tighter than customer login |

## Security headers (implemented, Phase 0)

`App\Http\Middleware\SecurityHeaders` (`apps/backend/app/Http/Middleware/SecurityHeaders.php`)
applies `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, `Permissions-Policy`, and
HSTS on secure requests to every `/api/*` response. No page-level CSP is set on the API (it
returns JSON, not HTML) — the Next.js app owns its own CSP.

## CORS

No `Access-Control-Allow-Origin: *` on private endpoints. Allowlist per environment, configured
via Sanctum's stateful-domains + Laravel's CORS config as those are wired up in Phase 1.

## Input validation & mass assignment

Every API input goes through Form Requests with explicit validation rules — never trusted from
the frontend alone. Model creation/updates use explicit field mapping, never
`$request->all()` passed straight into a model, for any endpoint that touches sensitive fields.

## File uploads (design; implemented starting Phase 3)

Documents/images are private object storage, never publicly addressable; served only via
short-lived signed URLs after a permission check. Executable uploads, double extensions, and
MIME-type mismatches are rejected; size limits are enforced.

## Logging

Never logged: passwords, OTPs, access/refresh tokens, `Authorization` headers, full bank
details, card data, identity document contents. Structured logs redact these fields at the
logging layer, not by convention alone.

## Error handling

Stack traces are never shown in production (`APP_DEBUG=false` in production — see
`docs/DEPLOYMENT.md`). Internal errors return a stable error code and a correlation ID; details
are logged server-side only.

## Encryption

HTTPS everywhere in production. Laravel's built-in encryption (`encrypted` cast /
`Crypt` facade) for any field needing at-rest protection beyond what the database provides.
Password hashing uses Laravel's configured algorithm (bcrypt/argon2), never MD5/SHA1.

## Data retention (design; policy finalized per-domain as each domain lands)

Retention windows for logs, GPS history, OTPs, sessions, uploaded documents, and deleted
accounts are configurable, not hardcoded, and documented per-domain once implemented. Account
deletion follows: request → identity confirmation → legal/financial retention check →
anonymization/deletion → completion. Financial ledger entries required for compliance are never
deleted outright even after account deletion; see `docs/COMPLIANCE.md`.

## Dependencies

Before adding any package: does Laravel/Next.js already provide this? Is it maintained? Does it
have a reasonable security history? Is it actually needed? No dependency bloat.
