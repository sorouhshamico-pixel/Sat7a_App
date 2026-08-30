# Security

## Status

Phase 1: authentication, OTP, sessions, and admin MFA are implemented. Phase 2: RBAC and audit
logging are implemented. Phase 23: CORS, log redaction, frontend security headers/CSP, and
baseline data-retention purging are implemented (see below in each case) — this document is
updated as each control is implemented, never a promise of controls that don't exist yet. See
`docs/SECURITY_HARDENING.md` for the full Phase 23 write-up, including the real bugs found while
verifying each of these live rather than just typechecking them.

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

## Authorization & RBAC (implemented, Phase 2)

Fine-grained permissions (`App\Domain\Authorization\Enums\PermissionName`) bundled into roles
(`App\Domain\Authorization\Enums\RoleName`), never a raw role-string check in application code.
See `docs/ROLES_PERMISSIONS.md` for the full catalog and mapping. Role assignment/revocation is
itself gated behind the `roles.manage` permission (granted only to `super_admin`) and always
audited.

## Cache deserialization

`config('cache.serializable_classes')` is `false` — Laravel's own secure default, meaning **no
PHP object may be unserialized out of a cache read**, a hardening against gadget-chain attacks if
`APP_KEY` ever leaked and an attacker could write to the cache store. This is intentional and
should stay `false`; the fix for hitting it is never to loosen it (see the real bug below).

**A real bug found while building Phase 17**: `App\Domain\Authorization\Concerns\HasRoles::
cachedPermissionSet()` — which backs `Gate::before` and therefore runs on nearly every
authenticated request — cached an array containing two `Illuminate\Support\Collection` objects.
Under the array cache store (what the entire test suite uses via `CACHE_STORE=array` in
`phpunit.xml`, since nothing is ever actually serialized there), this looked completely fine.
The first time this code path ran against a real serializing store (`CACHE_STORE=redis` — both
local dev's `.env` and, presumably, production), the *first* request (a cache miss, which only
writes) worked; every subsequent request reading that same cache key came back as a useless
`__PHP_Incomplete_Class` instead of a `Collection`, because the class-disallowing `unserialize()`
call silently degrades any disallowed object rather than throwing — so `hasPermission()`/
`hasRole()` would crash on essentially every second-and-later authenticated request. Invisible to
the whole test suite for the same reason it's invisible to `CACHE_STORE=array`: nothing there
ever round-trips through real serialization.

Fixed by never caching an object in the first place — `cachedPermissionSet()` now returns plain
arrays (`->pluck('name')->all()`, not `->pluck('name')`), which sidesteps the restriction
entirely rather than adding `Illuminate\Support\Collection` to an allow-list (loosening the
security hardening was never the right fix — there's no legitimate reason this cache entry needs
to hold an object instead of its underlying array). Regression-tested in
`tests/Unit/Domain/Authorization/CachedPermissionSetTest.php`, which deliberately forces the
`redis` cache store for one test (everything else stays on the fast `array` store) and asserts a
permission check survives a real write-then-separate-read cycle — the exact case the rest of the
suite structurally cannot catch. Grepped the rest of the codebase for other `Cache::remember`/
`Cache::put` call sites while fixing this; this was the only one.

## Audit logging (implemented, Phase 2)

`App\Domain\Audit\Services\AuditLogger` writes an immutable row to `audit_logs`
(`App\Domain\Audit\Models\AuditLog`) — actor, action, entity type/id, old/new values, reason, IP,
user agent — for every audited action. Currently wired into role assignment/revocation; extended
as later phases add their own sensitive actions (see docs/ROLES_PERMISSIONS.md §Audited actions
list). Rows are never updated or deleted by application code — a correction is a new row. No
super-admin action is exempt from this.

## Rate limiting baseline (tunable, implemented per-endpoint as each lands)

| Endpoint class | Limit |
|---|---|
| OTP send | 5 / hour |
| OTP verify | 5 attempts |
| Login | 10 / 15 min |
| Order create | 5 / 10 min — implemented, Phase 8 |
| Public estimate | 20 / min per user or IP — implemented, Phase 7 (`/pricing/quote`) |
| Maps (geocode/places/route) | 30 / min per user or IP — implemented, Phase 6 |
| Pricing quote | 20 / min per user or IP — implemented, Phase 7 |
| Admin login | strict, tighter than customer login |
| Driver location ping | 60 / min per driver — implemented, Phase 11 |
| Payment creation | 10 / 10 min per user or IP — implemented, Phase 12 |
| Payment webhook | 120 / min per IP — implemented, Phase 12 (public, unauthenticated) |

## Security headers (implemented, Phase 0 backend / Phase 23 frontend)

`App\Http\Middleware\SecurityHeaders` (`apps/backend/app/Http/Middleware/SecurityHeaders.php`)
applies `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, `Permissions-Policy`, and
HSTS on secure requests to every `/api/*` response. No page-level CSP is set on the API (it
returns JSON, not HTML).

The Next.js app's own headers (`apps/web/next.config.ts`) were added in Phase 23 — see
`docs/SECURITY_HARDENING.md` §CSP for the full write-up, including a real, empirically-verified
constraint: a strict `script-src 'self'` with no `'unsafe-inline'`/nonce genuinely breaks Next.js
App Router's own inline RSC-streaming hydration scripts (confirmed live via a Playwright
console-error capture, not assumed), so `script-src` carries `'unsafe-inline'` (matching Next's
own documented non-nonce CSP baseline) while every other directive — `object-src`,
`frame-ancestors`, `connect-src`, `worker-src`, etc. — stays strict. `'unsafe-eval'` is added only
in development (React's own debugging-overlay requirement, never shipped to production). HSTS is
production-only.

## CORS (implemented, Phase 23)

`config/cors.php` (paths `api/*` only) allows exactly one origin — `FRONTEND_URL`, the Next.js
app's own origin — via `Illuminate\Http\Middleware\HandleCors`, registered ahead of
`SecurityHeaders` in `bootstrap/app.php`. `supports_credentials` is `false`: this API never
receives cookie-based Sanctum SPA auth (see Authentication above) — every request the browser
ever makes goes through the Next.js app's own same-origin BFF route handlers, which attach the
Bearer token server-side, so a browser calling this API directly cross-origin isn't part of this
architecture's real traffic pattern at all. This was a real, if low-severity, gap before Phase
23 — no `config/cors.php` existed and `HandleCors` wasn't registered, so `/api/*` carried no
explicit origin policy either way. Verified live (not just configured): an OPTIONS preflight
against an auth-protected admin route returns a clean CORS response without ever reaching the
auth middleware, and Laravel's CORS middleware always echoes the one *configured* origin
regardless of the request's actual `Origin` header — safe, since a browser's same-origin check
compares the response header against its own origin, not against what the server received; see
`tests/Feature/Security/CorsTest.php`.

## Input validation & mass assignment

Every API input goes through Form Requests with explicit validation rules — never trusted from
the frontend alone. Model creation/updates use explicit field mapping, never
`$request->all()` passed straight into a model, for any endpoint that touches sensitive fields.

## File uploads (implemented, Phase 3)

Documents are stored on a dedicated private `documents` disk (local for now — see
`config/filesystems.php` and `docs/DEPLOYMENT.md`; swappable to S3-compatible storage by
changing that one config block, never application code), under a random ULID filename — the
client-supplied filename is kept only as display metadata, never used as the stored path.
Uploads are validated by actual file content (`mimes:pdf,jpg,jpeg,png` via PHP's fileinfo, not
the client-supplied extension), capped at 10MB, and rejected if the filename has more than one
extension (`App\Http\Requests\Api\V1\Providers\UploadDocumentRequest`).

There is no `Storage::temporaryUrl()` — instead every download goes through
`App\Http\Controllers\Api\V1\DocumentController@download`, which checks, fresh on every
request, whether the caller owns the underlying record or holds `documents.view` (or
`documents.view_sensitive` for the `identity` document type) before streaming the file. A
previously-valid link never stays valid after a permission change, because there is no link —
only an authenticated, re-checked request.

## Logging (implemented, Phase 23)

Never logged: passwords, OTPs, access/refresh tokens, `Authorization` headers, full bank
details, card data, identity document contents. `App\Logging\RedactSensitiveDataProcessor` — a
Monolog processor tapped onto every persisting log channel in `config/logging.php` — scans every
log record's context array (recursively, for nested payloads like a raw webhook body) for a
broad set of sensitive key fragments and replaces the value with `[redacted]`, regardless of
which action logged it. This closes a real gap: before Phase 23, the only redaction anywhere in
the codebase was one hand-rolled helper local to `ProcessPaymentWebhookAction`, giving no
systemic guarantee for any other `Log::*()` call site. Deliberately excludes short/generic
fragments like `code` or `pan` that would over-redact ordinary debugging fields (`status_code`,
`country_code`, ...) — see the processor's own docblock. Verified two ways, not just unit-tested
in isolation: `tests/Unit/Logging/RedactSensitiveDataProcessorTest.php` covers the processor's
logic directly, and `tests/Feature/Logging/LogRedactionWiringTest.php` writes through the actual
`single` channel to a real file and reads it back — proving the `config/logging.php` wiring
itself works, not just the class it points at.

## Error handling

Stack traces are never shown in production (`APP_DEBUG=false` in production — see
`docs/DEPLOYMENT.md`). Internal errors return a stable error code and a correlation ID; details
are logged server-side only.

## Encryption

HTTPS everywhere in production. Laravel's built-in encryption (`encrypted` cast /
`Crypt` facade) for any field needing at-rest protection beyond what the database provides.
Password hashing uses Laravel's configured algorithm (bcrypt/argon2), never MD5/SHA1.

## Data retention (baseline hygiene implemented Phase 23; full policy still per-domain design)

`App\Console\Commands\PurgeExpiredDataCommand` (`data:purge-expired`, scheduled daily at 03:00,
see `routes/console.php`) purges two bounded, uncontroversial categories past a configurable
window (`config/retention.php`, `RETENTION_OTP_CODES_HOURS`/`RETENTION_LOCATION_PINGS_DAYS` —
never hardcoded): OTP codes (functionally dead the moment they expire or are consumed; kept only
briefly for fraud investigation) and raw GPS location-ping history (a real privacy exposure if
kept indefinitely, and completely unbounded before this). Both are plain bulk deletes, verified
against a real database via `tests/Feature/Console/PurgeExpiredDataCommandTest.php` (creates real
rows, runs the command, asserts old ones are gone and recent ones survive) and confirmed against
this dev box's actual database, not just the test suite.

This does **not** attempt the full account-deletion/anonymization workflow below, which stays
design-only pending a compliance decision on retention windows for sessions, uploaded documents,
and deleted-account data. Account deletion follows: request → identity confirmation →
legal/financial retention check → anonymization/deletion → completion. Financial ledger entries
required for compliance are never deleted outright even after account deletion; see
`docs/COMPLIANCE.md`.

## Dependencies

Before adding any package: does Laravel/Next.js already provide this? Is it maintained? Does it
have a reasonable security history? Is it actually needed? No dependency bloat.

`composer audit` and `npm audit` were run as part of the Phase 23 hardening pass: zero known
vulnerability advisories in either dependency tree at that point in time. Re-run both whenever
dependencies change, not just once.
