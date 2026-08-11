# Dispatch Engine

## Status

Design document for Phase 9 (Dispatch & Matching), which depends on Phase 6 (Maps & Location
Foundation) and Phase 8 (Orders). Recorded here in Phase 0 so the shape is agreed early.

## Goal

For a given order, select the best eligible provider(s) to offer it to, automatically, without
letting two providers accept the same order.

## Candidate filtering (before scoring)

Never dispatched to: a suspended provider, a provider with expired required documents, a busy
tow truck, a truck in maintenance, or a truck incompatible with the required service type.
Candidates are drawn from a PostGIS `ST_DWithin` radius query first — not a Google Maps call —
to keep the initial candidate set cheap; only the short-listed candidates get a routing-API ETA
call (see `docs/ARCHITECTURE.md` §6 and cost controls below).

## Scoring

A configurable weighted score (not hardcoded in a controller), combining: distance,
availability, tow-truck capability match, provider rating, acceptance rate, cancellation-rate
penalty, response-time, and provider status. Weights live in configuration or the database, not
inline in code, so they can be tuned without a deploy.

## Dispatch waves

```text
Wave 1: top N candidates, wait a configurable timeout
Wave 2: next M candidates
Wave 3: expanded search radius
Then: manual dispatch escalation to an operations dispatcher
```

Wave sizes and timeouts are configuration, not magic numbers in the dispatch service.

## Concurrency

Acceptance is protected by a DB transaction + row lock (and a Redis distributed lock where
useful): acquire lock → begin transaction → reload order → verify still available → assign →
update status → commit → release lock. Two providers racing to accept the same order is a
required test scenario, not an edge case to skip (see `docs/TESTING_STRATEGY.md`).

## Manual fallback

Operations staff can always manually assign a provider, with a reason, subject to the same
eligibility checks (bypassing eligibility requires a distinct "override" permission and is
always audited — see `docs/ROLES_PERMISSIONS.md`).

## Cost control (Google Maps)

Nearby search always starts from PostGIS, never a bulk Google Distance Matrix call across all
providers. Only the short-listed candidates get a real routing-API ETA call. API key
restrictions, quotas, and billing alerts are configured at the Google Cloud project level (see
`docs/ARCHITECTURE.md` §6).
