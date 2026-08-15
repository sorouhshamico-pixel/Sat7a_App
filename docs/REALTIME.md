# Realtime

## Status

Implemented, Phase 10 (order status + dispatch offers), extended in Phase 11 (live location).
Laravel Reverb (self-hosted, Pusher-protocol-compatible WebSocket server) broadcasts order status
changes, new dispatch offers, and live tow-truck position updates on authenticated, per-entity
private channels. No frontend consumes this yet — that lands with the Next.js customer/provider
apps (Phase 19+) — this is backend infrastructure only.

## What's broadcast

- **`orders.{orderPublicId}`** (private) — `App\Domain\Orders\Events\OrderStatusChanged`,
  broadcast as `order.status_changed`. Dispatched from
  `App\Domain\Orders\Services\OrderStateMachine::transition()` — the single place every order
  status change goes through (see `docs/ORDER_LIFECYCLE.md`), so every transition from every
  domain (order cancellation, dispatch acceptance, manual assignment, and every later phase that
  touches order status) broadcasts automatically with no per-caller wiring.
- **`drivers.{driverPublicId}`** (private) — `App\Domain\Dispatch\Events\DispatchOfferCreated`,
  broadcast as `dispatch.offer_created`. Dispatched from
  `App\Domain\Dispatch\Actions\DispatchOrderAction` the moment each offer is created — this is
  what lets a driver's app show a new job without polling
  `GET /api/v1/drivers/me/dispatch-offers` (see `docs/DISPATCH_ENGINE.md`), closing the gap that
  phase explicitly left open ("a driver has to poll today").
- **`orders.{orderPublicId}`** (private, same channel — Phase 11) —
  `App\Domain\Tracking\Events\OrderLocationUpdated`, broadcast as `order.location_updated`.
  Dispatched from `App\Domain\Tracking\Actions\RecordLocationPingAction` whenever a driver's
  location ping lands while they have an order in an active trip status. See
  `docs/LIVE_LOCATION_TRACKING.md`.

All three event classes implement `Illuminate\Contracts\Events\ShouldDispatchAfterCommit` — the
actions that dispatch them (`DispatchOrderAction`, `RecordLocationPingAction`, and every caller of
`OrderStateMachine::transition()`: `CancelOrderAction`, `AcceptDispatchOfferAction`,
`ManuallyAssignOrderAction`, `AdvanceTripStatusAction`, ...) routinely run inside their own DB
transaction, sometimes nested. Without this, a broadcast could fire for a change that then rolls
back. The interface defers the broadcast until the outermost transaction actually commits,
regardless of nesting depth — no caller has to think about it.

## Authentication & authorization (implemented)

`POST /api/v1/broadcasting/auth` — registered explicitly via `->withBroadcasting()` in
`bootstrap/app.php` (not the `channels:` shorthand on `withRouting()`, whose default middleware
is the session-based `web` guard — useless for a Bearer-token API client) with
`['prefix' => 'api/v1', 'middleware' => ['auth:sanctum', 'abilities:*', 'throttle:api']]`, so a
client authenticates against this endpoint exactly like every other `/api/v1` route — including
rate limiting, which a route registered outside the normal `withRouting(api: ...)` group doesn't
pick up automatically and had to be added explicitly.

Channel authorization callbacks live in `routes/channels.php` (kept as one file rather than
scattered across domain service providers — it's the single place to check what's reachable over
a WebSocket connection):

- `orders.{orderPublicId}` — reachable by the owning customer, the assigned driver, staff of the
  assigned provider (via `users.provider_id`), or platform staff holding `orders.view_all`.
- `drivers.{driverPublicId}` — reachable only by that driver's own account.

No client can subscribe to an order or driver channel it doesn't own or isn't assigned to (see
`docs/ARCHITECTURE.md` §4) — every one of these rules has a dedicated test in
`tests/Feature/Realtime/ChannelAuthorizationTest.php`, including the negative cases (a stranger,
understaffed platform roles, a nonexistent order).

## Local dev setup

`REVERB_APP_ID`/`REVERB_APP_KEY`/`REVERB_APP_SECRET` in `.env` are locally-generated identifiers
for the **self-hosted** Reverb server — not a third-party credential, so generating and setting
them locally is safe (see `docs/SECURITY.md` §Secrets, which is about never committing *real*
external-service credentials; these are neither external nor committed — `.env` is gitignored).
Run `php artisan reverb:start` alongside `php artisan serve` (see `docs/DEPLOYMENT.md`).

## Testing note

The test suite defaults `BROADCAST_CONNECTION` to `null` (see `phpunit.xml`) so ordinary feature
tests that incidentally trigger a `ShouldBroadcast` event (creating an order, accepting a
dispatch offer, ...) never attempt a real network call — the null broadcaster's `broadcast()` is
a no-op. Two different techniques are used depending on what's being tested:

- **Broadcast dispatch** (`tests/Feature/Realtime/OrderBroadcastTest.php`,
  `DispatchBroadcastTest.php`, `LocationBroadcastTest.php`) — `Event::fake([...])` +
  `Event::assertDispatched(...)`, asserting the right event fired with the right
  channel/payload. This is the standard, fast approach and doesn't care which broadcaster driver
  is configured.
- **Channel authorization** (`ChannelAuthorizationTest.php`) — the null/log broadcasters don't
  implement real authorization logic at all (their `auth()` is a no-op that would make every
  channel look authorized), so exercising the actual callbacks in `routes/channels.php` needs
  the real Pusher-protocol-compatible driver Reverb uses. No network call is made for this —
  channel auth is pure local HMAC signing. The test switches `config(['broadcasting.default' =>
  'reverb'])` right before each auth call (not for the whole test, to keep unrelated setup steps
  from attempting real broadcasts) and re-`require`s `routes/channels.php`, since
  `Broadcast::channel()` registers each pattern onto whichever driver instance is current *at
  registration time* — the file was already `require`d once at application boot against the
  (still-default, null) connection, so a freshly-instantiated `reverb` driver starts with no
  channels registered at all.

## Not yet implemented

- No frontend client (Echo/Reverb JS) consumes any of this yet — Phase 19+ (Customer Web App
  Polish, Provider Web/PWA).
- No presence channels (e.g. "which admins are currently watching the ops dashboard") — no
  concrete need yet.
- Presence/typing-style channels for any future in-app chat — not part of this platform's
  current scope.
