# Security Hardening

## Status

Phase 23 — implemented (`apps/backend`, `apps/web`).

## Scope

`docs/SECURITY.md` has said since Phase 0 that "a dedicated Phase 23 security-hardening audit
happens before production readiness." This phase is that audit: reviewing every "design" or
"tunable" section that document had accumulated across Phases 0-22 and closing the concrete gaps
it found, rather than building new user-facing features. Every finding below was verified live —
against a running backend, a running frontend, or a real database — not just reasoned about from
documentation or shipped on the strength of a passing typecheck.

## Findings and fixes

### 1. CORS was never actually wired up

`docs/SECURITY.md` had claimed since Phase 1 that CORS was "configured via Sanctum's
stateful-domains + Laravel's CORS config as those are wired up in Phase 1" — but no
`config/cors.php` existed and `Illuminate\Http\Middleware\HandleCors` was never registered in
`bootstrap/app.php`. Low severity in this specific architecture (the browser never calls this API
directly — every request goes through the Next.js app's own same-origin BFF route handlers), but
a real, previously-undocumented gap: any future code path that *did* call the API straight from
browser JS would have had no explicit origin policy to rely on either way. Fixed with a narrow,
single-origin allowlist (`FRONTEND_URL` env var, `supports_credentials: false` since no
cookie-based Sanctum SPA auth is used here). See `docs/SECURITY.md` §CORS for the full write-up
and the live-verified reasoning about why Laravel's CORS middleware always echoing the one
configured origin (rather than reflecting the request's actual `Origin`) is still safe.

### 2. Log redaction was one hand-rolled helper, not a systemic guarantee

`docs/SECURITY.md`'s Logging section had claimed "structured logs redact these fields at the
logging layer, not by convention alone" — but the only redaction anywhere in the codebase was a
private `redact()` method local to `ProcessPaymentWebhookAction`, covering exactly one log call
site. Every other `Log::*()` call anywhere in the codebase had zero systemic protection against
accidentally logging a password, OTP, token, or bank detail. Fixed with
`App\Logging\RedactSensitiveDataProcessor`, a Monolog processor tapped onto every log channel
that actually persists a record. See `docs/SECURITY.md` §Logging for the fragment-matching design
(and why generic fragments like `code`/`pan` were deliberately excluded to avoid over-redacting
ordinary debugging fields) and the two-layer test coverage (processor logic in isolation, plus a
real file write-and-read-back proving the config wiring itself works).

### 3. The Next.js app had zero security headers, despite `docs/SECURITY.md` claiming otherwise

The backend's `SecurityHeaders` middleware doc comment said "the Next.js app owns its own CSP" —
but `next.config.ts` had no `headers()` at all before this phase. Fixed with a full header set
(CSP, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`,
production-only HSTS) mirroring the backend's own policy. The CSP specifically required real,
empirical verification rather than copying a generic "strict" template: a `script-src 'self'`
with no `'unsafe-inline'`/nonce was tried first and genuinely crashed the app's own hydration —
confirmed via a Playwright console-error capture showing `Invariant: Expected a request ID to be
defined for the document via self.__next_r`, not inferred from documentation. Next.js App Router
streams React Server Component payloads into the page via small inline `<script>` tags
(`self.__next_f.push(...)`) as an unavoidable part of normal hydration. The fully-strict
alternative (a per-request nonce generated in `src/proxy.ts`, per Next's own CSP guide) requires
forcing every page in the app to dynamic rendering — a real architectural cost across roughly 40
routes, several deliberately static today — judged out of scope for a hardening pass rather than
a rewrite. `script-src` therefore carries `'unsafe-inline'`, matching Next's own documented
non-nonce CSP baseline exactly; every other directive (`object-src 'none'`,
`frame-ancestors 'none'`, `connect-src 'self'`, `worker-src 'self'`, `base-uri 'self'`,
`form-action 'self'`) stays strict. `'unsafe-eval'` is added only in development (React's own
debugging-overlay requirement — confirmed live the same way, via a second console-error capture
after removing it) and dropped in production. Verified end to end: zero console errors on the
homepage under the real header in both `next dev` and a real `next start` production build (a
temporary second instance on a different port, not just `next build` succeeding), and the full
Playwright suite (customer/provider/admin auth flows, PWA manifest/service-worker/offline
behavior, SEO metadata) still passes with the header active site-wide.

### 4. No data-retention hygiene existed at all

`docs/SECURITY.md`'s Data retention section was pure design — "policy finalized per-domain as
each domain lands" — but nothing had actually landed: OTP codes and raw GPS location-ping history
(explicitly flagged sensitive/privacy-relevant in this same document's Data classification
section) accumulated indefinitely with no purge mechanism anywhere in the codebase. Fixed with a
bounded, uncontroversial baseline: `App\Console\Commands\PurgeExpiredDataCommand`, scheduled
daily, purging OTP codes past a short fraud-investigation grace window and location pings past a
90-day window (both configurable via `config/retention.php`, never hardcoded — see
`docs/SECURITY.md` §Data retention). Deliberately does **not** attempt the full account-deletion/
anonymization workflow that section also describes — a distinct, larger compliance feature
requiring policy decisions this phase didn't make. Verified against a real database, not just the
test suite: ran the command live on this dev box's own database after the test suite passed.

### 5. IDOR/authorization spot-check — no new findings

Reviewed every non-admin route in `routes/api.php` carrying a resource-id parameter. Every one is
either scoped under a `/me/...` prefix (resolved via `ResolvesProvider`/`ResolvesDriver`/
`ResolvesCustomer`, established since Phase 3/4/8 — never a bare cross-user lookup) or, for the
two genuinely shared endpoints, checked explicitly in the controller on every request:
`SessionController::destroy` scopes the delete through `$request->user()->tokens()->where('id',
$tokenId)` (a mismatched id just matches zero rows, never revealing whether another user's
session exists), and `DocumentController::download` re-checks ownership/permission fresh on every
request rather than trusting a previously-issued link (already documented in `docs/SECURITY.md`
§File uploads, and directly confirmed by reading the controller). No new bugs found — this section
exists to record that the check was actually done, not assumed from the pattern being followed
elsewhere.

### 6. Dependency audit — clean

`composer audit` and `npm audit` both report zero known vulnerability advisories as of this
phase. See `docs/SECURITY.md` §Dependencies.

## Not yet in this phase

- Nonce-based strict CSP (`script-src` without `'unsafe-inline'`) — would require migrating every
  route to dynamic rendering; a real architectural change, not a hardening tweak. Revisit if a
  concrete XSS-adjacent risk ever makes the trade-off worth it.
- The full account-deletion/anonymization compliance workflow (`docs/SECURITY.md` §Data
  retention) — only OTP codes and location pings are purged so far.
- Automated dependency-vulnerability scanning in CI (this phase's audit was a manual one-time
  run) — a natural fit for `docs/DEPLOYMENT.md`'s CI pipeline once one exists.
- Penetration testing / a formal OWASP ASVS checklist walkthrough — this phase was a targeted
  audit against this document's own previously-flagged gaps, not an exhaustive external-style
  security assessment.
- Rate-limit tuning beyond what's already documented in `docs/SECURITY.md`'s baseline table — no
  new limiters were added or changed this phase.
