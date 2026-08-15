# Live Location Tracking

## Status

Implemented, Phase 11. Two closely-related things landed together, because neither is useful
without the other: a driver reporting their live GPS position, and a driver advancing the trip
through its actual physical stages. The product requirement driving this
(`docs/PRODUCT_REQUIREMENTS.md`: "Driver — executes trips, updates status, shares location")
names both in the same breath.

## Trip status advancement (implemented)

Before this phase, an order could reach `provider_assigned` (Phase 9, dispatch acceptance) and
then go nowhere — nothing existed to advance it through `provider_en_route` →
`provider_arrived` → `vehicle_loading` → `trip_started` → `in_transit` → `vehicle_delivered` →
`completed`. No order could ever reach a real terminal state end-to-end.

`POST /api/v1/drivers/me/orders/{orderPublicId}/status` (`App\Domain\Orders\Actions\AdvanceTripStatusAction`)
closes that gap. It only exposes the seven-status forward chain above
(`AdvanceTripStatusAction::DRIVER_ADVANCEABLE_STATUSES`) — cancellation, dispute, and refund
states are never reachable through it, checked twice: once by an explicit whitelist, and
redundantly by `OrderStateMachine::transition()`'s own adjacency check, so a driver can never
skip a step even by passing a technically-valid future status.

Side effects, all in the same DB transaction:

- `orders.arrived_at` / `trip_started_at` / `completed_at` — reserved columns since Phase 8,
  finally populated at the point they're actually reached.
- The assigned tow truck's status advances in step (`App\Domain\Fleet\Enums\TowTruckStatus`):
  `reserved → en_route → arrived → loading → on_trip`, and — critically —
  **`on_trip → available` on `completed`**. Without this, a truck that finished a trip would stay
  stuck `on_trip` forever, permanently invisible to dispatch's nearby-candidate search. This was
  a real gap since Phase 9: nothing had ever moved a truck out of `reserved` except acceptance
  itself.
- The same resource-leak class of bug existed for **cancellation** — `CancelOrderAction`
  (Phase 8) never freed a truck that had already been reserved for the cancelled order. Fixed in
  this phase too: cancelling an order with an assigned truck returns it to `available` if its
  current status allows that transition.
- `OrderStatusChanged` broadcasts as usual (Phase 10) — trip status changes are order status
  changes, so this needed no new wiring.

## Live location (implemented)

`POST /api/v1/drivers/me/location` (`App\Domain\Tracking\Actions\RecordLocationPingAction`) — a
driver's app calls this continuously, independent of whether they currently have an order. It
always updates `tow_trucks.current_latitude`/`current_longitude`/`last_location_at`, which had no
real update path before this phase (only ever set directly in tests) — dispatch's
nearby-candidate search (Phase 9) depends on this data being fresh.

When the driver also has an order in one of the trackable statuses (`provider_assigned` through
`in_transit` — before that there's no truck to track yet, after `vehicle_delivered` the trip is
functionally over), the same ping is additionally recorded as an append-only breadcrumb in
`order_location_pings` and broadcasts live as `OrderLocationUpdated` on the same
`orders.{orderPublicId}` channel Phase 10 introduced — reusing it exactly as
`docs/REALTIME.md` anticipated ("Phase 11 will very likely reuse the `orders.{orderPublicId}`
channel for a new event type").

`GET /api/v1/customers/me/orders/{orderPublicId}/location` (and the admin equivalent,
`GET /api/v1/admin/orders/{order}/location`, gated by `orders.view_all`) return the current
position plus a bounded recent path (`?limit=`, capped at 500 points):

```json
{
  "current": {
    "latitude": 24.72, "longitude": 46.68, "heading": 90, "speed_kmh": 40,
    "recorded_at": "...", "source": "trip_ping"
  },
  "path": [ { "latitude": ..., "longitude": ..., "recorded_at": "..." }, ... ]
}
```

`current.source` is `trip_ping` once at least one breadcrumb exists for the order, or
`tow_truck_last_known` as a fallback immediately after acceptance, before the driver's app has
sent its first update — better than nothing, worse than a live fix, and labelled so a client can
tell the difference.

## Geography (same trade-off as Phase 9)

`order_location_pings` uses plain `latitude`/`longitude` decimals, not PostGIS — consistent with
`tow_trucks.current_latitude`/`current_longitude` and the same documented, temporary trade-off
from Phase 9 (see `docs/DISPATCH_ENGINE.md` and `docs/DEPLOYMENT.md` §One-time PostGIS setup).
Nothing here does a spatial query — it's pure read/write of point data — so there's no immediate
pressure to convert, unlike the nearby-search case.

## Rate limiting

`location-ping` named limiter (60/min per driver) — looser than the general API default since a
driver's app pings this frequently while on a trip, but still bounded.

## Not yet implemented

- No trip-path visualization on a map — that's frontend work (Phase 19+), this phase only
  exposes the data.
- No geofencing (e.g. auto-detecting "arrived" from proximity to the pickup point) — the driver
  reports arrival manually; automatic detection is a plausible future enhancement, not built
  speculatively here.
- No distance/duration-traveled computation from the breadcrumb trail (e.g. for a
  dispute/review) — the raw data exists for a later phase to use, but nothing computes from it
  yet.
- No driver-initiated cancellation endpoint — `OrderCancelledBy::Provider` has existed as a valid
  enum case since Phase 8, but nothing exposes it; out of scope for this phase.
