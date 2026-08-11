# Monitoring

## Status

Phase 0 provides a health-check endpoint only. Structured logging, error reporting (Sentry),
queue/websocket health, and backup procedures are built out starting Phase 25 (Production
Readiness), with earlier phases adding domain-specific observability as they land (e.g.
dispatch logs in Phase 9, payment failure tracking in Phase 12).

## Health checks (implemented)

`GET /api/v1/health` (`apps/backend/app/Http/Controllers/Api/V1/HealthController.php`) checks
database and Redis connectivity and returns `200` (`status: ok`) or `503` (`status: degraded`)
with a per-dependency boolean breakdown. It intentionally does not leak connection strings,
stack traces, or credentials — safe to expose to infrastructure health probes, not a general
public status page.

Laravel's built-in `/up` endpoint (configured in `bootstrap/app.php`) remains available as the
framework-level liveness probe.

## Planned (later phases)

- Structured JSON logs with PII redaction for key domain events (e.g. `order.accepted`).
- Sentry (or equivalent) wired via environment variables, off by default.
- Queue failure monitoring (Horizon dashboard in staging/production; see `docs/DEPLOYMENT.md`
  for the Windows/local limitation).
- WebSocket (Reverb) connection health.
- Slow-query and N+1 query monitoring.
- Backup policy: daily encrypted database backups, off-site, with a documented and periodically
  tested restore procedure — not just "a backup exists."
