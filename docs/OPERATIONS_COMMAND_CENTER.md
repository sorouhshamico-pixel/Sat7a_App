# Operations Command Center

## Status

Phase 17 — implemented (`apps/web/src/app/admin`). This is the first phase to build any real
UI in `apps/web`; everything before it was backend-only (see `docs/ROADMAP.md`).

## Scope

The admin web app for platform staff (dispatchers, customer support, operations managers) to
monitor and act on live operations: orders (including manual dispatch overrides) and disputes.
Finance/compliance-specific admin screens (payments, settlements, document verification,
provider approval) are Phase 18's job, not this one — this phase is bounded to the operational
subset already exposed by the existing `/api/v1/admin/orders`, `/api/v1/admin/orders/.../
dispatch/...`, and `/api/v1/admin/disputes` endpoints (Phases 8, 9, 15).

## Authentication (implemented)

Admin login is always two steps — password, then mandatory MFA (see
`App\Domain\Authentication\Actions\AdminLoginAction`, `docs/SECURITY.md` §Authentication) — and
the frontend mirrors that exactly:

1. `POST /api/auth/login` (a Next.js Route Handler, not called directly from a client
   component) forwards `{email, password}` to Laravel's `/api/v1/auth/admin/login` and returns
   `{stage, token}` straight to the browser. `token` here is one of the backend's short-lived,
   narrowly-scoped tokens (`mfa-setup` or `mfa-challenge` ability only) — it is held only in the
   login page's own React state (`src/app/admin/login/page.tsx`), **never** written to a cookie.
2. Depending on `stage`:
   - `mfa_setup_required` → `POST /api/auth/mfa/setup` fetches `{secret, otpauth_url}`; the page
     renders a QR code (`qrcode` package, generated client-side from the `otpauth_url` — no
     network call, no vendor) plus the raw secret for manual entry, then
     `POST /api/auth/mfa/confirm` with the 6-digit code.
   - `mfa_challenge_required` → `POST /api/auth/mfa/challenge` with the 6-digit code directly.
3. Either success path returns a **fully-privileged** Sanctum token from Laravel. That's the
   one and only moment `src/lib/session.ts` writes the real session: an httpOnly, `SameSite=lax`
   cookie (`admin_session`, 8-hour `Max-Age` matching the backend's own token TTL) that client
   JavaScript can never read. A second, non-httpOnly cookie (`admin_user`) holds just
   `{id, name, email}` for display (e.g. "signed in as ...") — nothing sensitive, never used for
   authorization.
4. `POST /api/auth/logout` reads the session cookie, calls Laravel's `/api/v1/auth/logout` to
   revoke the token server-side, then clears both cookies.

The Sanctum token **never reaches client-side JavaScript** after login completes — every
authenticated admin API call goes through a single generic proxy,
`src/app/api/backend/[...path]/route.ts`, which reads the httpOnly cookie server-side and
forwards the request to Laravel with `Authorization: Bearer <token>` attached. Adding a new
admin screen never needs a new Route Handler; it just calls `apiGet`/`apiPost` (`src/lib/api/
client.ts`), which hits `/api/backend/...`.

`src/proxy.ts` (Next.js 16 renamed `middleware.ts` → `proxy.ts` — see that file's own comment)
gates every `/admin/*` page: no session cookie → redirect to `/admin/login`; session cookie
present on `/admin/login` → redirect to `/admin`. This is a presence-only check — an
expired/revoked token is only actually caught when a page's data fetch hits the backend proxy
and gets back `UNAUTHENTICATED`, which is unhandled today (see "Not yet in this phase" below).

A file-location trap worth remembering: with a `src/` directory, `proxy.ts` (like the old
`middleware.ts` before it) **must** live at `src/proxy.ts`, not the project root — Next.js
silently ignores a root-level one and every `/admin/*` route renders with no auth gate at all.
This was caught by `e2e/admin-auth.spec.ts`, not by any unit/component test, because a mocked
`fetch()` can't exercise real routing/redirect behavior — worth remembering before trusting
vitest coverage alone for anything proxy/middleware-related.

## Orders (implemented)

`GET /api/v1/admin/orders` (list, `?status=` filter, paginated) and `GET .../orders/{id}`
(detail) via `src/app/admin/(dashboard)/orders/page.tsx` and `.../orders/[id]/page.tsx`. Order
detail also surfaces dispatch offers (`GET .../orders/{id}/dispatch-offers`) and three actions,
gated client-side only by the order's current status (`src/lib/orders.ts`'s
`DISPATCHABLE_ORDER_STATUSES`/`CANCELLABLE_ORDER_STATUSES` — informational only, the backend is
the real authority):

- Retry automatic dispatch (`POST .../dispatch/retry`).
- Manually assign a tow truck (`POST .../dispatch/assign`) — the truck is entered by its public
  ID in a plain text field; there's no truck search/autocomplete yet (see below), since no admin
  "list tow trucks" endpoint exists to back one.
- Cancel the order (`POST .../cancel`), requiring a reason.

## Disputes (implemented)

`GET /api/v1/admin/disputes` (list, `?status=` filter) and `GET .../disputes/{id}` (detail) via
`src/app/admin/(dashboard)/disputes/page.tsx` and `.../disputes/[id]/page.tsx`. Detail exposes
the single generic status-advance action (`POST .../disputes/{id}/status`), matching the
backend's own narrow state machine: `open` can only move to `under_review`; from there,
`resolved`/`rejected` both require non-empty resolution notes.

## UI foundation (implemented)

Hand-rolled, Tailwind-based primitives in `src/components/ui/` (Button, Input, Card, Badge,
Alert, Spinner) — not a full shadcn/ui install (no component registry access in this
environment), but built to the same visual/API shape `docs/ARCHITECTURE.md` §8 calls for, so a
real shadcn/ui migration later is a styling change, not a rewrite. TanStack Query
(`src/components/query-provider.tsx`) handles all server state (list/detail fetches, mutations,
cache invalidation on action success) — no hand-rolled loading/error state juggling per page.

## Not yet in this phase

- No handling for an expired/revoked session mid-use — a stale cookie gets an
  `UNAUTHENTICATED` envelope back from `/api/backend/...` today, which the pages don't yet catch
  and redirect on (only the initial page-load proxy check is enforced).
- No tow-truck search/autocomplete for manual dispatch assignment (types a raw ID) — needs an
  admin "list tow trucks" backend endpoint that doesn't exist yet.
- No real-time updates (WebSocket/Reverb) on the orders list/detail — every screen is
  request/response, refreshed on mutation only. Phase 10's realtime infrastructure exists on the
  backend; wiring the frontend up to it is a reasonable next increment, not done here.
- No dashboard metrics/counts beyond two static navigation cards.
- No permission-based UI hiding — every logged-in admin/staff sees every button; the backend's
  own permission checks are the real authorization boundary, a denied action just surfaces the
  resulting 403 as an inline error rather than being hidden in advance.
- No Finance & Compliance screens (payments, settlements, provider/document verification,
  reviews moderation) — Phase 18's scope, not this one.
