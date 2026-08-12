# Order Lifecycle

## Status

Implemented, Phase 8. The state machine, transitions, domain events, and the customer-facing
create/show/list/cancel/timeline endpoints exist as described below. Dispatch (Phase 9) will
drive the `searching_provider` → `provider_assigned` → ... states automatically; for now those
transitions exist in the matrix and are exercised directly in tests via
`App\Domain\Orders\Services\OrderStateMachine`, but nothing in the product yet triggers them —
an order created today simply sits in `pending` until Phase 9 lands.

## States

```text
pending
searching_provider
provider_assigned
provider_en_route
provider_arrived
vehicle_loading
trip_started
in_transit
vehicle_delivered
completed
```

Cancellation:

```text
cancelled_by_customer
cancelled_by_provider
cancelled_by_admin
expired
```

Special:

```text
disputed
refund_pending
refunded
```

`draft`/`quote_ready` from the original design were dropped: the public `/api/v1/pricing/quote`
and `/api/v1/maps/route` endpoints (Phase 6/7, both guest-usable) already fully cover the
"build an estimate before committing" need, so an `Order` row is only ever created once a
customer is ready to actually book — there's no intermediate persisted draft state.

## Rules (implemented)

- Real state machine, not a free-text status column: `App\Domain\Orders\Enums\OrderStatus`
  defines the full transition matrix (`allowedTransitions()`/`canTransitionTo()`), and every
  status change — now and in every later phase — goes through
  `App\Domain\Orders\Services\OrderStateMachine::transition()`, which validates against that
  matrix and atomically records a row in the append-only `order_status_history` table in the
  same DB transaction. No code sets `orders.status` directly. Both the allowed and the
  *rejected* transitions are unit tested (`tests/Unit/Domain/Orders/OrderStatusTest.php`).
- **Price is never trusted from the client.** `App\Domain\Orders\Actions\CreateOrderAction`
  always recomputes the route distance via the `RoutingProvider` abstraction (Phase 6) from the
  pickup/dropoff coordinates and always recomputes the price via
  `App\Domain\Pricing\Actions\GenerateQuoteAction` (Phase 7) — a client-supplied `quoted_price`
  or `total` is simply never read. The resulting `PricingSnapshot` is stored verbatim on
  `orders.pricing_snapshot`, so later pricing-rule changes never move a historical order's price
  (see `docs/DATABASE_SCHEMA.md` §Immutability).
- A situation flagged `requires_manual_quote` (severely damaged vehicle, no wheels, underground
  parking, ...) is rejected at order-creation time with `MANUAL_QUOTE_REQUIRED` rather than
  silently falling back to an automated price that doesn't suit the situation — there is no
  automated order-creation path for these yet; the customer is told to contact support. A staff
  workflow for manually-quoted orders is deferred until a proven need exists (no such workflow
  exists today, so building one speculatively was avoided — see the "explicitly deferred"
  philosophy in `docs/ROADMAP.md`).
- Cancellation always records `cancelled_by`, `cancellation_reason`, `cancelled_at`, and a
  `cancellation_fee` column (present, always `0` for now — no cancellation-fee policy exists
  yet). `App\Domain\Orders\Actions\CancelOrderAction` enforces that a **customer** can only
  self-cancel from `OrderStatus::customerCancellable()` — `pending` through `vehicle_loading` —
  never once `trip_started` or later, since the vehicle may already be on the truck. Admin
  cancellation (`orders.cancel` permission) goes through the same state-machine matrix, which
  independently blocks cancelling an order that's already `trip_started` or later — a trip
  already underway is a dispute/refund concern, not a cancellation one.
- Domain events (`App\Domain\Orders\Events\OrderCreated`, `OrderCancelled`) are raised by the
  actions, never inline in a controller. `App\Domain\Orders\Listeners\LogOrderLifecycleListener`
  does structured logging today (matching the `order.accepted` shape in `docs/MONITORING.md`) —
  it stands in for real customer/provider notifications until the Notifications domain lands in
  Phase 16.
- A guest can build a quote (Phase 6/7) but an order only becomes real after authentication —
  `POST /api/v1/customers/me/orders` requires a fully-privileged customer token; there is no
  unauthenticated order creation (see `docs/PRODUCT_REQUIREMENTS.md`).
- Every customer-facing order endpoint resolves through `App\Http\Controllers\Concerns\ResolvesCustomer`
  and is additionally scoped to the caller's own `orders()` relation before looking up a
  `public_id` — never a bare `{order}` route parameter — so cross-customer access isn't
  reachable through these endpoints at all (mirrors the Phase 5 vehicles/saved-locations
  pattern). Admin endpoints (`/api/v1/admin/orders`) use ordinary route-model binding, since
  `orders.view_all`/`orders.cancel` are legitimately platform-wide, not ownership-scoped.

## Endpoints (implemented)

| Method | Path | Auth |
|---|---|---|
| POST | `/api/v1/customers/me/orders` | customer token, rate-limited (`order-create`: 5/10min) |
| GET | `/api/v1/customers/me/orders` | customer token |
| GET | `/api/v1/customers/me/orders/{orderPublicId}` | customer token |
| GET | `/api/v1/customers/me/orders/{orderPublicId}/timeline` | customer token |
| POST | `/api/v1/customers/me/orders/{orderPublicId}/cancel` | customer token |
| GET | `/api/v1/admin/orders` | `orders.view_all` |
| GET | `/api/v1/admin/orders/{order}` | `orders.view_all` |
| POST | `/api/v1/admin/orders/{order}/cancel` | `orders.cancel` |

## Not yet implemented

- Dispatch/matching (Phase 9) — nothing currently transitions an order out of `pending`
  automatically, and `assigned_provider_id`/`assigned_driver_id`/`assigned_tow_truck_id` columns
  exist on `orders` but are always `null` today.
- Payments (Phase 12) — `payment_method` is always `cash`, `final_price` is always `null`.
- Provider-side order visibility/acceptance (depends on Phase 9).
- Disputes/refunds workflow (Phase 15) — the `disputed`/`refund_pending`/`refunded` states exist
  in the matrix but nothing drives them yet.

## Concurrency (design — implemented alongside Phase 9)

Order assignment must never let two providers accept the same order. Will be implemented via DB
transaction + row locking (and a Redis distributed lock if needed) around: acquire lock → begin
transaction → reload order → verify it's still assignable → assign → update status → commit →
release lock, with dedicated concurrency tests (two providers racing to accept) — see
`docs/TESTING_STRATEGY.md` and `docs/DISPATCH_ENGINE.md`. Not a concern yet since nothing
assigns orders to providers until Phase 9.
