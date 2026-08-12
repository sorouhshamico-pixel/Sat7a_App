# Dispatch Engine

## Status

Implemented, Phase 9. Runs automatically right after order creation and is driven forward by
driver responses and a scheduled command — see the flow below. Depends on Phase 6 (Maps &
Location Foundation) and Phase 8 (Orders).

## PostGIS still isn't installed — see the trade-off this made

This project's own conventions (`docs/DATABASE_SCHEMA.md` §Geography) say nearby-location
queries must use PostGIS `ST_DWithin`, never manual Haversine math in PHP. PostGIS is still not
installed on the dev machine (see `docs/DEPLOYMENT.md`), and — separately — `tow_trucks` still
has no `location geography(Point,4326)` column (that migration was deferred in Phase 6 too). Both
are needed to do this properly, not just the extension.

Phase 6 handled its own PostGIS blocker by deferring the affected work entirely. Deferring here
would have meant not shipping a dispatch engine at all — this is one of the platform's core
features, not an optional add-on, and every later phase (Realtime, Live Location Tracking,
Payments) assumes orders actually get matched to providers. Asked how to proceed, the call made
was: implement the full dispatch engine now behind a `NearbyTowTruckFinder` interface, with the
only implementation being `App\Domain\Dispatch\Adapters\Haversine\HaversineNearbyTowTruckFinder`
— a documented, temporary in-app Haversine query instead of PostGIS. This is a deliberate,
flagged exception to the stated convention, not an oversight. Once PostGIS is active: add the
`location geography` column to `tow_trucks`, write a `PostGisNearbyTowTruckFinder`, and swap the
binding in `App\Providers\DispatchServiceProvider` — nothing calling the interface needs to
change.

## Candidate filtering (implemented)

`HaversineNearbyTowTruckFinder::find()` starts from every tow truck with `status = available`,
a driver assigned, and a known location, then filters to: the driver is `active` and currently
`is_available`, the provider's status `canReceiveOrders()` (i.e. `approved`), and
`service_capabilities` includes the order's `service_type`. Distance is computed via the
Haversine formula from the order's pickup point; results are sorted nearest-first and capped at
the wave's candidate limit. See the trade-off note above for why this isn't a PostGIS
`ST_DWithin` query yet.

## Scoring (not yet implemented)

Today's ranking is distance-only (nearest first). The full weighted score from the original
design — distance, availability, tow-truck capability match, provider rating, acceptance rate,
cancellation-rate penalty, response-time, provider status — needs acceptance-rate/
cancellation-rate/response-time data this project doesn't track anywhere yet (no dispatch
history existed before this phase). Revisit once `dispatch_offers` has accumulated real history
to score against, rather than inventing weights with no data behind them.

## Dispatch waves (implemented)

Configured in `config/dispatch.php`, not hardcoded:

```text
Wave 1: radius 5km,  up to 5 candidates
Wave 2: radius 15km, up to 5 candidates
Wave 3: radius 30km, up to 10 candidates
Then: manual dispatch escalation to an operations dispatcher
```

`App\Domain\Dispatch\Actions\DispatchOrderAction` walks forward through configured waves —
starting at whichever wave it's asked to try — until one produces at least one candidate, or
every configured wave is exhausted, in which case `orders.manual_dispatch_required` is flipped
true. A wave that finds zero candidates creates no offer rows and isn't itself recorded as the
order's `current_dispatch_wave`; only a wave that actually produced offers (or the final
exhausted attempt) is.

Called: automatically at wave 1 right after order creation
(`App\Domain\Dispatch\Listeners\StartDispatchListener`, subscribed to `OrderCreated` — a
dispatch failure is logged and never breaks the order-creation request itself, see
`docs/TESTING_STRATEGY.md` §Failure-mode testing); immediately at the next wave when a driver
rejects the last pending offer in the current wave
(`App\Domain\Dispatch\Actions\RejectDispatchOfferAction`); by the scheduled command below when
offers go unanswered; and from wave 1 by an operations dispatcher's manual "retry" action
(`POST /api/v1/admin/orders/{order}/dispatch/retry`).

## Offer expiry & escalation (implemented)

Each offer carries an `expires_at` (`dispatch.offer_ttl_seconds`, default 60s).
`dispatch:escalate-stale-offers` runs every minute (`routes/console.php`) via
`App\Domain\Dispatch\Services\DispatchEscalationService`: expires pending offers past their TTL,
then — for any order whose current wave has no pending offers left — starts the next wave.

## Concurrency (implemented)

`App\Domain\Dispatch\Actions\AcceptDispatchOfferAction` row-locks the order and the offer inside
a DB transaction, re-verifies both are still in an acceptable state after the lock is acquired
(order still `searching_provider`, offer still `pending` and not expired), assigns
provider/driver/tow-truck to the order, transitions the order to `provider_assigned`, marks the
offer `accepted`, and marks every other pending offer for the same order `superseded` — all in
the same transaction. A second acceptance attempt on an already-resolved offer gets
`DISPATCH_OFFER_NO_LONGER_AVAILABLE` (409). Tested by accepting once (succeeds) and immediately
attempting to accept the same offer again (rejected) — this exercises the guard logic a real
concurrent race would hit, since PHPUnit can't run true parallel requests (see
`docs/TESTING_STRATEGY.md` §Concurrency).

## Manual fallback (implemented)

`App\Domain\Dispatch\Actions\ManuallyAssignOrderAction` — an operations dispatcher assigns a
specific tow truck directly (`POST /api/v1/admin/orders/{order}/dispatch/assign`, gated by the
`orders.assign` permission already seeded to `dispatcher`/`operations_manager` in Phase 2). Still
enforces the normal eligibility checks (truck available and capable, provider approved, driver
active) — bypassing eligibility entirely is a distinct "override" action, not implemented,
reserved for a proven need. Always audited
(`App\Domain\Audit\Services\AuditLogger`, action `orders.manually_assigned`).

## Driver-facing endpoints (implemented)

`GET /api/v1/drivers/me/dispatch-offers` (pending, unexpired offers for the caller),
`POST /api/v1/drivers/me/dispatch-offers/{offerPublicId}/accept`,
`POST .../reject` — all resolved via `App\Http\Controllers\Concerns\ResolvesDriver`, scoped to
the caller's own `dispatchOffers()` relation, never a bare `{driver}`/`{offer}` route parameter
that isn't ownership-checked.

## Cost control (Google Maps)

Not yet relevant: the Haversine finder makes no external API calls at all (it's a local
in-database computation), so there's no Google Maps cost to control at the candidate-filtering
stage today. Once dispatch needs a real driving-route ETA (rather than straight-line distance)
for the short-listed candidates, that call goes through the existing `RoutingProvider`
abstraction (Phase 6) — only for the short list, never a bulk scan across all providers — per the
original design.
