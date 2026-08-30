# Monitoring

## Status

Phase 0 provided a health-check endpoint. Phase 25 (Production Readiness) added error reporting
(Sentry, off by default), a real backup/restore procedure, and infrastructure config templates
for queue/websocket supervision — see below and `docs/PRODUCTION_READINESS.md` for the full
write-up. Queue/websocket dashboard health and slow-query/N+1 automated detection remain design
only (see Planned below).

## Health checks (implemented)

`GET /api/v1/health` (`apps/backend/app/Http/Controllers/Api/V1/HealthController.php`) checks
database and Redis connectivity and returns `200` (`status: ok`) or `503` (`status: degraded`)
with a per-dependency boolean breakdown. It intentionally does not leak connection strings,
stack traces, or credentials — safe to expose to infrastructure health probes, not a general
public status page.

Laravel's built-in `/up` endpoint (configured in `bootstrap/app.php`) remains available as the
framework-level liveness probe.

## Error reporting (implemented, Phase 25)

`sentry/sentry-laravel` (`config/sentry.php`) — off by default: the SDK is a genuine no-op with
no `SENTRY_LARAVEL_DSN` set (no network calls, no measurable overhead), the same "real vendor,
off until configured" pattern this project uses for payments/SMS/push/email/maps, except this is
an actual library dependency rather than an interface+fake-adapter pair, since Sentry has no
meaningful fake mode to build against and the SDK itself already provides the off switch. Hooks
into `App\Exceptions\ApiExceptionRenderer`'s existing `report($e)` call automatically via
Laravel's own exception-reporting pipeline — no application code changes needed once a real DSN
exists. `send_default_pii` stays `false` (matches Phase 23's redaction stance); breadcrumb/tracing
SQL-binding capture stays off by default too, so query parameter values (which can include phone
numbers, tokens) aren't sent to Sentry even if it's later turned on without someone deliberately
opting into that.

The `report($e)` call this hooks into had never actually been tested end to end before Phase
25 either — see `tests/Feature/ApiExceptionRendererTest.php`, which triggers a real unhandled
exception and asserts the sanitized envelope, the correlation ID, and that no stack
trace/message/class name leaks, confirming Sentry's install didn't change any of that.

## Backup policy (implemented, Phase 25 — with one caveat)

`infrastructure/backup-database.sh` / `infrastructure/restore-database.sh` — `pg_dump --format=
custom` to a timestamped file, optional AES-256-CBC encryption at rest via `BACKUP_ENCRYPTION_KEY`
(OpenSSL, `pbkdf2`), with a clearly marked extension point for off-site upload once a real target
exists (no S3/equivalent credentials exist yet — same "no real vendor" pattern as everywhere
else). Restore drops and recreates a named target database (never defaults to overwriting the
live one) and runs `pg_restore` into it.

**What was actually verified, and what wasn't, on this dev machine**: the backup half was
verified for real — ran `pg_dump` against the live dev database, confirmed the output file's
`PGDMP` magic header and a correct OpenSSL `Salted__` header after encryption, and successfully
decrypted it back. The `pg_restore`/`psql`-driven restore half could **not** be executed on this
specific Windows machine — both binaries are blocked by a Windows Application Control Policy
(`"An Application Control policy has blocked this file"`, confirmed via both Git Bash and native
PowerShell, ruling out a sandbox restriction rather than a real OS-level one) — the same class of
environmental blocker as the PostGIS one (`docs/DEPLOYMENT.md`), not something fixable from
inside this project. As a substitute, a full logical restore-and-verify round trip **was**
completed: `pg_dump --inserts` (plain SQL) executed via PHP's PDO pgsql driver directly (no
blocked binaries involved), restoring into a fresh throwaway database and comparing row counts
against the source across five tables — all matched exactly. This proves the backup data itself
is sound and restorable; the piece that remains genuinely unverified is specifically the
`pg_restore` CLI invocation syntax in the shipped script, not the underlying data. Re-run
`infrastructure/restore-database.sh` once, for real, against a scratch database on the actual
Linux target (staging) before ever relying on it during a real incident — that machine won't have
this Windows-specific restriction.

## Planned (later phases / infrastructure-dependent)

- Frontend error reporting (`@sentry/nextjs` or equivalent) — only the backend got Sentry this
  phase. The API is where the higher-severity failure modes live (payments, dispatch, auth), and
  wiring a second SDK plus source-map upload configuration for the Next.js app is a distinct,
  sizable addition, not a natural extension of the backend work — deliberately scoped out rather
  than done half-heartedly.
- Structured JSON logs with PII redaction for key domain events — Phase 23's
  `RedactSensitiveDataProcessor` already redacts sensitive fields at the logging layer for every
  log call project-wide (see `docs/SECURITY.md` §Logging); a dedicated structured event-log format
  for specific domain events (e.g. `order.accepted`) beyond what's already logged wasn't built.
- Queue failure monitoring via a real Horizon dashboard — Horizon itself needs a Linux host
  (`pcntl`/`posix`, see `docs/DEPLOYMENT.md`); `infrastructure/systemd-horizon.service` supervises
  it once one exists, but the dashboard itself wasn't stood up or screenshotted from this Windows
  dev box.
- WebSocket (Reverb) connection health — no dedicated health-check endpoint for Reverb
  specifically; `/api/v1/health` doesn't check it.
- Slow-query and N+1 query monitoring in production (automated, ongoing) — Phase 24 did a
  one-time manual audit (`docs/PERFORMANCE.md`); this item is about continuous, automated
  detection (e.g. a query-count budget assertion in CI, or an APM tool), not repeated here.
- Backup restore *drill* on the actual Linux target — see the caveat above.
