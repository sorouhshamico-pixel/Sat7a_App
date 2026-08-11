# API Specification

## Status

Phase 0: conventions only. The actual OpenAPI 3.x document will live at
`packages/api-contracts/openapi.yaml` and be generated/maintained starting with Phase 1
(Authentication), growing as each phase adds endpoints. It must stay in sync with the real
routes in `apps/backend/routes/api.php` — never a description of an intended API that doesn't
exist yet.

## Versioning

Every route lives under `/api/v1/...`. A future `/api/v2/...` can be added without breaking
existing Next.js or Flutter clients still on v1. Version is a URL prefix, not a header, so it's
unambiguous in logs, docs, and client generation.

## Response envelope

Every endpoint returns the same shape (`App\Http\Responses\ApiResponse`,
`apps/backend/app/Http/Responses/ApiResponse.php`):

Success:

```json
{
  "data": {},
  "meta": {},
  "errors": null
}
```

Error:

```json
{
  "data": null,
  "meta": {},
  "errors": [
    { "code": "ORDER_ALREADY_ACCEPTED", "message": "..." }
  ]
}
```

Error `code` values are stable, machine-readable constants — Flutter and Next.js clients branch
on `code`, never on the Arabic/English `message` text. Error codes are documented per-endpoint
in the OpenAPI spec as they're introduced.

## Authentication

Laravel Sanctum. Customers and providers/drivers authenticate via mobile number + OTP; Admin
authenticates via email/password + mandatory MFA (see `docs/SECURITY.md`). Sanctum was chosen
over Passport because the platform doesn't need third-party OAuth2 clients — first-party SPA
(Next.js) and first-party mobile apps (future Flutter) are Sanctum's designed use case.

## Identifiers

Public-facing entities (orders, providers, trips, payments, disputes, ...) are addressed by
ULID `public_id` in the API, never the internal auto-increment ID (see
`docs/DATABASE_SCHEMA.md`). Every request is still authorized via Policies regardless of ID
type — an unguessable ID is not treated as an authorization control.

## Pagination

Cursor or page-based pagination (Laravel's built-in paginator) on every list endpoint, returned
in the `meta` object (`current_page`, `per_page`, `total`, or cursor equivalents). No unbounded
list endpoints.

## Validation errors

Field-level validation failures use HTTP 422 with an `errors` array where each entry's `code` is
`VALIDATION_FAILED` and `meta` carries the field-level breakdown — exact shape finalized and
documented in the OpenAPI spec once the first real endpoints (Phase 1 Auth) land.

## Rate limiting

Per-endpoint throttling documented alongside each endpoint in the OpenAPI spec (see
`docs/SECURITY.md` §Rate limiting for the baseline policy: OTP send/verify, login, order
creation, public estimate, and admin login all have distinct, stricter limits than the general
API default).

## Mobile readiness

Nothing here is web-specific. Auth flows, error codes, realtime channel authorization, and push
notification abstraction are all designed to work identically from a future Flutter client
consuming the same `/api/v1` surface (see `docs/ARCHITECTURE.md` §1 and §8, and
`docs/ROADMAP.md`).
