# Security

## Status

Phase 0: baseline controls only (security headers, rate-limit scaffolding, secrets policy, data
classification). Authentication/OTP/session/MFA controls land in Phase 1; a dedicated
Phase 23 security-hardening audit happens before production readiness. This document is updated
as each control is implemented — it is not a promise of controls that don't exist yet.

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

## Authentication (design; implemented in Phase 1)

- Customer & Provider/Driver: mobile number + OTP.
- Admin: email/username + strong password + mandatory MFA (TOTP) + recovery codes.
- No super-admin action bypasses audit logging, ever.

## OTP handling (design; implemented in Phase 1)

Hashed at rest (never plain text), short expiration, capped verify attempts, capped send
frequency, resend cooldown, IP + device rate limiting, one-time use, invalidated immediately on
successful verification, never written to logs.

## Sessions (design; implemented in Phase 1)

Users can see registered devices, log out a specific device, log out everywhere, and terminate
suspicious sessions. Session identifiers rotate on authentication.

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
