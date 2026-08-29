# Customer Web App

## Status

Phase 19 — implemented (`apps/web/src/app/(customer)`).

## Scope

The customer-facing counterpart to Phase 17/18's admin app, sharing the same Next.js project
(`apps/web`) via a new `(customer)` route group. Covers the core customer journey end to end: a
guest builds a price quote, authenticates with phone + OTP, adds a vehicle, creates an order,
tracks it to completion, pays in cash, and leaves a review or raises a dispute. Reuses Phase
17/18's UI kit, TanStack Query setup, and BFF pattern unchanged; this phase's real work is
extending that BFF to a *third* trust tier (public/guest) alongside the existing two
(admin/customer).

## Dual-purpose, three-tier backend proxy

Phase 17 built one generic proxy (`src/app/api/backend/[...path]/route.ts`) for the admin app
only. This phase extended it to serve all three tiers from the same route file:

- **Admin paths** (`admin/...`) — gated on the existing `admin_session` cookie, unchanged.
- **Customer paths** (everything else, by default) — gated on a new `customer_session` cookie
  (`src/lib/customer-session.ts`, mirroring `session.ts`'s pattern: httpOnly, 30-day TTL, plus a
  non-httpOnly `customer_user` display cookie).
- **Public paths** (`maps/`, `pricing/quote`, `cities`, `health`) — no cookie required at all.
  Necessary because a guest must be able to search addresses and get a price quote *before*
  authenticating (`docs/PRODUCT_REQUIREMENTS.md` §Core customer journey) — the quote-builder
  homepage is the unauthenticated landing page.

`src/proxy.ts` (edge middleware) was similarly extended in place to gate both `/admin/*` and the
new customer-protected prefixes (`/orders`, `/vehicles`) in one file, redirecting to `/login` with
a `?next=` parameter that survives a full round trip through OTP verification.

Customer auth is architecturally simpler than admin's: a single step (phone + OTP →
`App\Domain\Authentication\Actions\VerifyOtpAction` immediately issues a fully-privileged 30-day
Sanctum token), versus admin's two-step password + MFA challenge. New route handlers
(`src/app/api/auth/customer/{otp/send,otp/verify,logout}/route.ts`) mirror the shape of the
admin auth handlers but skip the MFA-challenge step entirely.

Any future phase adding a fourth session type (e.g. Phase 20's provider PWA) should extend this
same three-tier pattern rather than building a parallel proxy.

## Guest quote → booking handoff

The homepage (`src/app/(customer)/page.tsx`) lets a guest pick pickup/dropoff addresses, a
service type, and a vehicle category, then calls the public `maps/route` and `pricing/quote`
endpoints to show an instant price. "Book now" round-trips the full selection
(`pickup_lat/lng/address`, `dropoff_lat/lng/address`, `service_type`, `vehicle_category`) as URL
query parameters to `/orders/new`. Because `/orders/*` is proxy-gated, an unauthenticated click
redirects to `/login?next=/orders/new?...params`, and the entire destination — including the
quote's query string — is restored after OTP verification. No cross-page state store was needed
for this handoff.

## No map SDK — text-based address search

No real Google Maps API key exists (`GOOGLE_MAPS_API_KEY` is empty); the backend's
`MapsServiceProvider` already falls back to deterministic fake adapters
(`App\Domain\Maps\Adapters\Fake\*`) for geocoding, places, and routing. Rather than embed a map
SDK against a key that doesn't work, `src/components/address-search.tsx` uses the existing public
`maps/places/autocomplete` and `maps/places/{placeId}` endpoints as a debounced text-search
input — consistent with the project's established "no real vendor, fake/log adapter in dev"
pattern used everywhere else (payments, SMS, push, email, WhatsApp). A pin-dropping map picker is
deferred until a real Maps vendor is configured.

## Cash-only payment

`FakePaymentGateway::createPayment()` returns `pending` with an unreachable fake checkout URL for
card payments, requiring a webhook callback that nothing in local dev can trigger — so a card
payment UI would be permanently stuck in "pending" with no way to complete it. Phase 19
deliberately scopes payment UI to cash only (`POST customers/me/orders/{id}/payments` with
`{method: "cash"}`, immediately `captured`). Card payment UI is deferred until a real gateway (or
a webhook-simulation endpoint) exists.

## Live tracking via polling, not WebSocket

The order-detail page (`src/app/(customer)/orders/[id]/page.tsx`) polls
`GET customers/me/orders/{id}/location` every 5 seconds (`refetchInterval: 5000`) while the order
is in a trackable status, rather than consuming Reverb/Echo over a WebSocket. `docs/REALTIME.md`
already frames frontend WebSocket consumption as a later phase's concern, and standing up Echo
client wiring is substantial new infrastructure disproportionate to this phase's scope. Polling
reuses the same REST endpoint the admin app already calls.

## Real bugs found and fixed while building this

All three were found via live Playwright verification against a real order progressed through
its full lifecycle (dispatch → trip statuses → completion) with real curl calls, not via mocked
unit tests:

1. **Address-search re-search-after-selection loop.** Selecting a suggestion set `query` to its
   full description for display, but `query` was also the debounced search effect's dependency —
   so the selection immediately re-triggered a new autocomplete search using the just-selected
   text, reopening the dropdown with a different set of suggestions right after the user picked
   one. Fixed with a `skipNextSearch` ref, set before the display-only `setQuery()` call and
   consumed at the top of the search effect.

2. **Race allowing "Get Quote" before address selection resolved.** Selecting a suggestion
   triggers an async place-details fetch before the parent's `pickup`/`dropoff` state is set. A
   fast click on "احصل على السعر" could land between the visible input update and that state
   update, showing a confusing "select pickup and destination first" error even though the input
   already displayed the address. Fixed by disabling the button until both `pickup` and `dropoff`
   are set (`disabled={loading || !pickup || !dropoff}`) — a genuine UX fix that also happens to
   make the flow deterministic for automated clicks, since Playwright's `.click()` auto-waits for
   a disabled button to become enabled.

3. **"Invalid Date" rendered in the tracking UI.** `GetOrderLocationAction`'s `current.recorded_at`
   is `null` when the endpoint falls back to the tow truck's last-known position (source
   `tow_truck_last_known`, before any real trip ping exists) — confirmed via a raw response
   showing `"last_location_at":null`. The page did `new Date(recorded_at ?? "").toLocaleTimeString(...)`,
   and `new Date("")` renders the literal text "Invalid Date" to the customer. Fixed by branching:
   show a formatted time when `recorded_at` is present, otherwise the fallback string "موقع تقريبي
   لآخر معرفة به" (approximate last-known location).

## Not yet in this phase

- No card payment UI (see above — the fake gateway's `pending` + webhook flow isn't completable
  in dev).
- No real-time WebSocket tracking — 5-second REST polling only.
- No pin-dropping map picker — text-based address autocomplete only.
- No push/SMS notification consumption in the customer app itself (the backend already sends
  these per Phase 16; the web app doesn't yet surface a notification center).
- No order-history pagination controls (the list endpoint supports it; the UI doesn't expose it
  yet, matching the same gap already noted for several Phase 18 admin lists).
- No profile/settings page (name/phone editing, notification preferences).
