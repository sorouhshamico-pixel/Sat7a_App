# Production Readiness

## Status

Phase 25 — the final phase of the 25-phase roadmap (`docs/ROADMAP.md`). Implemented
(`apps/backend`, `infrastructure/`).

## Scope

`docs/DEPLOYMENT.md` has said since Phase 0 that production readiness is "not evaluated until
Phase 25." This is that evaluation: a checklist walk of everything Phase 0's own deploy checklist
named — `APP_DEBUG=false`, HTTPS enforced, security headers verified, rate limits verified,
Horizon/queue/Reverb supervised, scheduled backups with a tested restore procedure — closing what
was still just a plan, and being explicit about the two items genuinely blocked by this specific
dev machine rather than papering over them.

## Checklist

| Item | Status |
|---|---|
| `APP_DEBUG=false` in production | Already correctly implemented (Phase 1) — `app()->hasDebugModeEnabled()` gate in `ApiExceptionRenderer`; **now also regression-tested** (`tests/Feature/ApiExceptionRendererTest.php`, previously untested) |
| HTTPS enforced | Implemented Phase 25 — `trustProxies()` + `TRUSTED_PROXIES`, real gap found and fixed (see `docs/SECURITY.md` §HTTPS enforcement) |
| Security headers verified | Implemented Phase 23, **now verified under a real reverse-proxy header shape** (Phase 25 — HSTS specifically depends on the trustProxies fix above to ever fire in production) |
| Rate limits verified | Three previously-unverified limiters (admin login, location ping, payment creation) now regression-tested against real 429s — see `docs/SECURITY.md`'s rate-limiting table |
| Horizon supervised (Linux host) | `infrastructure/systemd-horizon.service` template |
| Reverb behind a WebSocket-capable reverse proxy | `infrastructure/nginx-reverse-proxy.conf` template |
| Queue workers supervised with restart policies | `infrastructure/systemd-queue-worker.service` template (Horizon's non-Windows-blocked fallback) |
| Scheduled backups, tested restore procedure | `infrastructure/{backup,restore}-database.sh` — real, working scripts; backup verified live against the dev database, restore verified via a workaround (see `docs/MONITORING.md` §Backup policy for the exact caveat) |
| Error reporting | Sentry wired, off by default (`docs/MONITORING.md` §Error reporting) |

## The two-tier "found a real gap" pattern continues

Every phase from 17 onward that touched infrastructure/security/performance has found at least
one thing that was documented as done but wasn't actually verified, or configured but not
enforced. Phase 25 is no exception, and arguably found the highest-severity one yet:

**`trustProxies()` was never configured, at all**, despite `docs/DEPLOYMENT.md` already
describing a reverse-proxy deployment topology and `SecurityHeaders`' HSTS header being marked
"implemented since Phase 0." Two concrete, deployable-today consequences: HSTS would never
actually fire in the real deployment topology this project's own docs describe, and — more
seriously — every per-IP rate limiter (OTP send, admin login's IP bucket, location ping/payment
creation's unauthenticated fallback) would collapse onto a single shared bucket for every real
user behind that proxy, since `$request->ip()` would always resolve to the proxy's own address.
One misbehaving client could have rate-limit-locked out every legitimate user platform-wide the
moment this app went live behind any standard reverse proxy or load balancer. Found by walking
`docs/DEPLOYMENT.md`'s own checklist line by line rather than assuming each item was covered by
an earlier phase's work, fixed with an env-configurable `TRUSTED_PROXIES` list, and verified live
(`tests/Feature/Security/TrustedProxiesTest.php`) rather than trusted on the strength of the
config line alone — matching every other "verify the actual behavior" finding this project has
made since Phase 17.

## A blocker, handled the same way the PostGIS one was

`pg_restore`/`psql` are both blocked on this specific Windows dev machine by a Windows
Application Control Policy — confirmed genuinely OS-level (not a Claude Code sandbox restriction)
by reproducing the exact same failure through both Git Bash and native PowerShell, the latter
reporting the policy by name. This is the same category of environmental blocker as the PostGIS
one `docs/DEPLOYMENT.md` has documented since Phase 6: real, external, not fixable from inside
this project, and not something to route around with something artificial. `pg_dump` itself
works fine and was verified for real. For the restore half specifically, a legitimate substitute
verification was completed instead of leaving it entirely unverified — see `docs/MONITORING.md`
§Backup policy for the full account of what that proved and what it didn't. The shipped
`restore-database.sh` still uses the standard, correct `pg_restore` — that's the right tool for a
real Linux target, where this Windows-only restriction won't exist; only the *verification* of
that exact script needed a substitute path on this specific machine.

## Real errors found and fixed this phase

1. `trustProxies()` missing entirely — see above. The single highest-value finding of this
   phase.
2. The unhandled-exception → correlation-ID → sanitized-500 path in `ApiExceptionRenderer` had
   never been tested at all, despite existing since Phase 1 and being the most important error
   path in the whole API (the one every genuinely-unexpected production failure goes through).
   Added `tests/Feature/ApiExceptionRendererTest.php`; confirmed it still behaves correctly with
   Sentry installed.
3. Three rate limiters (admin login, location ping, payment creation) were defined and documented
   but never exercised by a request in a test — all three turned out to be correctly enforced
   once actually checked, but "defined" and "enforced" were two different claims until this phase
   made them the same one.

## Not yet in this phase

- Nonce-based strict CSP, the full account-deletion/anonymization compliance workflow, and formal
  penetration testing — already deferred by `docs/SECURITY_HARDENING.md` (Phase 23) and still out
  of scope here. (Automated dependency-vulnerability scanning in CI, also deferred by Phase 23,
  was closed shortly after this phase — see Post-roadmap below.)
- Real load/stress testing against production-scale data — still deferred by
  `docs/PERFORMANCE.md` (Phase 24); needs a staging environment with realistic data volume.
- An actual off-site backup upload target — no S3-equivalent credentials exist yet; the
  extension point is clearly marked in `infrastructure/backup-database.sh`.
- A `pg_restore`-specific restore drill on a real Linux host — see the blocker section above.
  This is the one item on `docs/DEPLOYMENT.md`'s original checklist this phase could not fully
  close from this machine; everything else on that checklist is genuinely done.
- Horizon dashboard screenshots/walkthrough, since Horizon itself can't run on this Windows dev
  box at all (`docs/DEPLOYMENT.md` §Horizon on Windows, pre-existing, not new to this phase).
- A dedicated Reverb connection-health check on `/api/v1/health` — the endpoint still only
  checks database and Redis.

## Post-roadmap: CI hardening

This project's 25 planned phases are complete as of this document. The first thing done
afterward, still following the same "audit what's already there, fix what's found" methodology:
`.github/workflows/{backend,frontend}.yml` existed and already ran the full local gate
(Pint/Larastan/PHPUnit, ESLint/tsc/Prettier/Vitest/build) on every push, but had two real gaps.

1. **No dependency-vulnerability scanning at all in CI** — `composer audit`/`npm audit` had only
   ever been run manually, during Phases 23-25, with no guarantee either check would be re-run on
   a future dependency bump. Both workflows now run their respective audit on every push.
2. **The Playwright e2e suite had never run in CI, at all** — despite being the tool that caught
   several of this project's real bugs first-hand (a strict CSP breaking Next.js's own hydration
   in Phase 23, `proxy.ts` silently blocking public PWA/SEO metadata routes in Phases 21-22, the
   original `proxy.ts`-vs-`middleware.ts` rename bug in Phase 17). A regression in any of those
   areas would previously have shipped undetected until someone happened to test it manually.
   Added a new `e2e` job to `frontend.yml`. Checked first whether this needed the Laravel backend
   running too (it doesn't — every current spec covers Next.js-app-only behavior: `proxy.ts`
   redirects, PWA manifest/icons/service-worker, SEO metadata, security headers), so the job only
   needs Node + Playwright's own browser install, no Postgres/Redis/backend services. Verified
   locally by simulating the exact CI codepath (`CI=true npm run test:e2e`, which forces
   Playwright's `webServer` to start a genuinely fresh `next dev` instance rather than reusing one
   already running, matching what a clean CI runner does) — all 22 tests passed, and the spawned
   server was confirmed torn down afterward.

## Post-roadmap: the CI-hardening commit itself was never actually checked against real CI

Every phase in this project, including this one, ended with "full local gate green" as the
completion criterion — Pint/Larastan/PHPUnit and ESLint/tsc/Prettier/Vitest/build, run on this
persistent dev machine. What had never once been checked, in 25 phases plus the CI-hardening work
above, was whether pushed commits actually produced a *passing* GitHub Actions run. Running
`gh run list` for the first time (prompted by nothing more than continuing the same
audit-everything methodology past the point of "no more roadmap phases left") showed both
`backend.yml` and `frontend.yml` had been failing on every push for at least the prior two phases,
including the CI-hardening commit itself. Two distinct, real, previously-undetected bugs, both
invisible locally because a persistent dev machine's state (an already-generated `.env`, an
already-built `.next/types`) is not what a fresh checkout starts with:

1. **Backend**: `composer install`'s post-autoload-dump hook (`artisan package:discover`) fully
   boots the framework, which resolves the default broadcaster
   (`config/broadcasting.php` → `'default' => env('BROADCAST_CONNECTION', 'reverb')`, true even
   with zero `.env` present) and crashes constructing a Pusher client with a null auth key —
   `RuntimeException: Failed to create broadcaster for connection "reverb"`. The workflow copied
   `.env.example` to `.env` *after* `composer install`, so on every fresh CI checkout this step
   never had a chance to succeed. Fixed by reordering `backend.yml` so `.env` is copied and given
   placeholder, CI-only Reverb identifiers (self-hosted-server IDs, not a third-party credential —
   see `docs/DEPLOYMENT.md` §Realtime (Reverb)) before `composer install` runs. Reproduced the
   exact crash locally first (`rm .env; php artisan package:discover --ansi`), confirmed the fix
   resolves it, then restored the real dev `.env` before re-running the full local gate.
2. **Frontend**: `tsc --noEmit` ran before anything had ever generated `.next/types`, so the
   Next-magic `LayoutProps` type used in `src/app/layout.tsx` could never resolve on a fresh
   checkout. Fixed by changing `package.json`'s `typecheck` script to `next typegen && tsc
   --noEmit`. Verified locally from a clean-deleted `.next` state.

Both fixes verified against the real GitHub Actions run after pushing (`gh run watch`), not just
locally — `backend.yml`'s `test` job and `frontend.yml`'s `test`/`e2e` jobs all passed for the
first time in this project's history. The standing lesson, consistent with every other finding
this project has made since Phase 17: a green local gate was never proof of a green CI run, and
from this point on "done" for any future change means checking `gh run list`/`gh run watch`
against the real push, not just the local terminal.

## Post-roadmap: Dependabot

`composer audit`/`npm audit` (added earlier in this same post-roadmap pass) only catch known CVEs
in whatever version is already pinned — they say nothing about a dependency simply going stale.
Added `.github/dependabot.yml` (composer for `apps/backend`, npm for `apps/web`, github-actions
for the workflow files themselves, weekly, capped at 5 open PRs each) so version bumps get
proposed automatically rather than relying on someone remembering to run `composer outdated`/`npm
outdated`. This is meaningfully more useful now than it would have been earlier in the post-roadmap
work above: every PR Dependabot opens touches `apps/backend/**` or `apps/web/**`, which is exactly
what triggers `backend.yml`/`frontend.yml` — so these bumps now land against a CI pipeline that
actually runs and actually passes, gating them for real instead of on the strength of a version
number alone.

Dependabot's very first scan opened 12 PRs immediately, proving the config out end to end. 11 were
real, safe patch/minor bumps (Laravel framework 13.24→13.29, Reverb, Predis, `pragmarx/google2fa`,
`react-hook-form`, `vitest`, `@types/react-dom`, `eslint-config-next` patch, and the three
`actions/*` v4→v7 bumps, which also clear the "Node.js 20 is deprecated" annotation both workflows
had been printing) — checked individually via `gh pr checks`, then merged (squash, branch deleted)
after confirming the *cumulative* post-merge state on `main` also passes both workflows end to end,
not just each PR in isolation. One (`eslint` 9→10) genuinely fails: `eslint-config-next@16.3.0`'s
bundled `eslint-plugin-react` calls `context.getFilename()`, an API ESLint 10 removed —
`TypeError: contextOrFilename.getFilename is not a function`, confirmed via the real CI log, not
assumed. Not fixable in this repo; commented with the root cause and closed rather than left open
indefinitely, since `eslint-config-next` needs to bump its own `eslint-plugin-react` dependency
first. Re-syncing local `vendor/`/`node_modules` to match the bumped lockfiles and re-running the
full local gate (Pint, Larastan, PHPUnit — 264 tests; ESLint, `next typegen && tsc --noEmit`,
`next build`, Vitest — 37 tests) also passed clean, confirming no local/CI drift.
