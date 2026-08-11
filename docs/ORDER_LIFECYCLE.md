# Order Lifecycle

## Status

Design document for Phase 8 (Orders), which implements the real state machine, transition
tests, and domain events. Recorded here in Phase 0 so Dispatch (Phase 9), Pricing (Phase 7),
Payments (Phase 12), and Realtime (Phase 10) are designed against an agreed shape from the
start.

## States (draft — finalized when Phase 8 lands)

```text
draft
quote_ready
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

## Rules

- Implemented as a real state machine (explicit transition table), not a free-text status
  column mutated from anywhere. Every transition is unit tested, including the transitions that
  must be *rejected*.
- Cancellation always records `cancelled_by`, `reason`, `fee` (even if zero in the MVP), and a
  timestamp.
- Domain events are raised at each meaningful transition (`OrderCreated`,
  `OrderSearchingStarted`, `ProviderMatched`, `ProviderAcceptedOrder`, `ProviderArrived`,
  `TripStarted`, `TripCompleted`, `OrderCancelled`, `PaymentCaptured`, `RefundIssued`) and
  side effects (notifications, dispatch waves, ledger entries) are handled by listeners/jobs —
  never inline in the controller.
- A guest can build a quote (pickup, destination, vehicle, service type, price estimate) but an
  order only becomes real/actionable after authentication — no unauthenticated order creation
  (see `docs/PRODUCT_REQUIREMENTS.md`).

## Concurrency

Order assignment must never let two providers accept the same order. Implemented via DB
transaction + row locking (and a Redis distributed lock if needed) around: acquire lock → begin
transaction → reload order → verify it's still assignable → assign → update status → commit →
release lock. This has dedicated concurrency tests (two providers racing to accept) — see
`docs/TESTING_STRATEGY.md` and `docs/DISPATCH_ENGINE.md`.
