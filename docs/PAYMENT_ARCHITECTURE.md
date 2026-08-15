# Payment Architecture

## Status

Implemented, Phase 12. `App\Domain\Payments` — the gateway abstraction, a fake adapter, payment
creation, webhook-driven confirmation, and admin-initiated refunds. No real gateway account
exists yet, so `PAYMENT_GATEWAY_DRIVER` stays `fake` in every environment today, including
production (see `docs/SECURITY.md` §Secrets) — a real adapter (Moyasar/Tap/HyperPay) is added to
`App\Providers\PaymentServiceProvider` once credentials exist, with no changes needed anywhere
that calls the interface.

## Abstraction (implemented)

Business logic never talks to a payment gateway SDK directly —
`App\Domain\Payments\Contracts\PaymentGateway`:

```php
createPayment(Payment $payment): PaymentGatewayResult
capture(Payment $payment): PaymentGatewayResult
refund(Payment $payment, int $amountMinorUnits): RefundGatewayResult
verifyWebhookSignature(Request $request): bool
parseWebhookEvent(Request $request): WebhookEvent
getPaymentStatus(Payment $payment): PaymentGatewayResult
```

Deliberately narrower than the Phase 0 sketch: there's no separate `authorize` method. Nothing in
this product needs a hold-then-capture-later flow — `createPayment` covers this platform's whole
charge flow (initiate → gateway-hosted checkout → webhook confirms capture), and `capture` alone
already covers a gateway that confirms synchronously. Refined once the phase actually landed,
same as `draft`/`quote_ready` order states were dropped in Phase 8.

`App\Domain\Payments\Adapters\Fake\FakePaymentGateway` is the only implementation today. `cash`
"pays" instantly (collected in person, no real checkout to run — this uniformly folds the
existing cash flow through the same `Payment` record system as card payments). Card methods come
back `pending` with a fake checkout URL, and confirmation arrives via the exact same
webhook-confirms-capture path a real gateway would use — including a genuine HMAC signature
check (`X-Fake-Signature` against `services.payments.fake.webhook_secret`), not a stub that
always returns true.

## Methods (implemented)

`App\Domain\Payments\Enums\PaymentMethod`: `mada`, `visa`, `mastercard`, `apple_pay`, `cash`.
Deliberately separate from `App\Domain\Orders\Enums\OrderPaymentMethod` (Phase 8) — that's the
coarse cash-or-card choice made at order creation; this is the specific method recorded once an
actual payment attempt exists.

## Card data (implemented)

No PAN, CVV, or full card details are ever stored — `payments.card_brand`/`card_last_four` only,
populated from the gateway's response, never from client input directly.

## Payment states (implemented)

```text
pending, authorized, captured, failed, cancelled, partially_refunded, refunded
```

A real state machine (`App\Domain\Payments\Enums\PaymentStatus::allowedTransitions()`,
`canTransitionTo()`), mirroring `App\Domain\Orders\Enums\OrderStatus` — same pattern, same
project-wide rule against free-text status columns. Every transition — from
`CreatePaymentAction`'s synchronous cash capture and from `ProcessPaymentWebhookAction`'s
async card confirmation alike — goes through the single choke point
`App\Domain\Payments\Services\PaymentStateMachine::transition()`, which validates the matrix,
records the right timestamp column, updates `orders.final_price` on capture, and broadcasts
(`PaymentCaptured`/`PaymentFailed` on the `orders.{orderPublicId}` channel — see
`docs/REALTIME.md`).

Order status and payment status are related but not the same state machine, exactly as
originally designed: capturing a payment never moves `orders.status` — a driver marking a trip
`completed` (Phase 11) and a customer's payment being `captured` are independent facts about the
same order.

**When a payment can be created**: only once `orders.status` is `vehicle_delivered` or
`completed` (`OrderStatus::isPayable()`) — matching `docs/PRODUCT_REQUIREMENTS.md` ("Trip
completes; customer pays") literally. This is a deliberately narrow scope: no
pre-authorization/deposit flow exists or was asked for.

## Webhooks (implemented)

`POST /api/v1/webhooks/payments/{gateway}` — public (the gateway itself calls it, so it can
never require a Sanctum token; trust comes entirely from
`PaymentGateway::verifyWebhookSignature()`), rate-limited generously (`webhook`: 120/min per IP,
since a real gateway retries deliveries). `App\Domain\Payments\Actions\ProcessPaymentWebhookAction`:

1. Verifies signature — invalid signature is rejected with `WEBHOOK_SIGNATURE_INVALID` (401).
2. Idempotent per `(gateway, event_id)` — `payment_webhook_events` is the dedup ledger; a
   duplicate delivery is recognized and short-circuited before any payment state changes, no
   matter how many times the gateway retries. (A true concurrent double-delivery race — two
   identical webhooks processed in parallel — isn't additionally guarded against beyond the
   `exists()` check; a documented simplification, not expected to matter in practice since
   gateways don't fire the same event ID in parallel.)
3. Payload is stored redacted of sensitive values before persisting (same rule as logging, see
   `docs/SECURITY.md` §Logging) — defensive, even though the fake gateway's own payloads never
   contain card numbers.
4. An unrecognized `gateway_payment_id` or a non-transitionable status is logged and the event is
   still marked processed — never surfaced as an error back to the gateway, which would just
   trigger pointless retries.

## Idempotency (implemented)

`POST .../orders/{order}/payments` accepts an `Idempotency-Key` header — a replayed key returns
the original `Payment` instead of creating a second one, so a client retry (timeout, double-tap)
can never double-charge. Separately, `App\Domain\Payments\Actions\CreatePaymentAction` also
blocks a *new* payment attempt outright while any pending/authorized/captured/refunded payment
already exists for the order — a `failed`/`cancelled` prior attempt doesn't block a retry.

## Endpoints (implemented)

| Method | Path | Auth |
|---|---|---|
| POST | `/api/v1/customers/me/orders/{orderPublicId}/payments` | customer token, rate-limited (`payment-create`: 10/10min), `Idempotency-Key` header supported |
| GET | `/api/v1/customers/me/orders/{orderPublicId}/payments` | customer token |
| POST | `/api/v1/webhooks/payments/{gateway}` | none — signature-verified internally |
| GET | `/api/v1/admin/payments` | `payments.view` |
| GET | `/api/v1/admin/payments/{payment}` | `payments.view` |
| POST | `/api/v1/admin/payments/{payment}/refund` | `payments.refund` (audited) |

`payments.view`/`payments.refund` were already seeded to `finance_officer` in Phase 2 — no
permission catalog changes needed for this phase.

## Refunds (implemented)

Admin/finance-initiated only (`App\Domain\Payments\Actions\RefundPaymentAction`) — a captured
payment can be refunded more than once (partial refunds tracked in `refunds`, append-only), and
the action rejects an amount exceeding what remains available
(`payment.amount - sum(succeeded refunds)`). Every refund is audit-logged
(`App\Domain\Audit\Services\AuditLogger`, action `payments.refunded`).

**Not wired to cancellation**: `App\Domain\Orders\Actions\CancelOrderAction` does not
automatically refund a captured payment. `orders.cancellation_fee` has been `0` for every order
since Phase 8 with no cancellation-fee policy ever defined — auto-refunding the full amount on
every cancellation would encode a business decision this project hasn't actually made. A human
decides for now via the admin refund endpoint.

## Not yet implemented

- A real gateway adapter (Moyasar/Tap/HyperPay) — no credentials exist yet.
- Financial ledger / commission / settlements (Phase 13/14) — a captured payment doesn't yet
  generate any ledger entries or trigger a provider payout calculation.
- Automatic refund-on-cancellation (see above) — pending a cancellation-fee policy.
- A driver/provider-facing "mark cash collected" confirmation step — today any authenticated
  owner of the order (the customer) creates the `cash` payment record themselves; there's no
  separate provider-side attestation.
- Reconciliation job polling `getPaymentStatus()` for payments stuck `pending` — the method
  exists on the interface (and the fake adapter implements it as a stateless pass-through of the
  payment's own local status, since there's no real remote gateway to poll) but nothing calls it
  on a schedule yet.
