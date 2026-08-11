# Payment Architecture

## Status

Design document for Phase 12 (Payments). Recorded here in Phase 0 so the gateway abstraction is
agreed before any gateway-specific code is written.

## Abstraction

Business logic never talks to a payment gateway SDK directly. A `PaymentGatewayInterface`
(conceptually: `createPayment`, `authorize`, `capture`, `refund`, `verifyWebhook`,
`getPaymentStatus`) sits between Orders/Payments domain code and gateway adapters
(`MoyasarGateway`, `TapGateway`, `HyperPayGateway`, ...). Development and CI use a fake gateway
adapter — no live gateway secrets are ever required to run tests (see `docs/SECURITY.md`
§Secrets, and `PAYMENT_GATEWAY_DRIVER=fake` in `.env.example`).

## Methods

`mada`, `visa`, `mastercard`, `apple_pay`, `cash`, plus whatever else the chosen gateway
supports later.

## Card data

No PAN, CVV, or full card details are ever stored server-side. Card capture uses the gateway's
hosted components / tokenization; only safe metadata (gateway token, brand, last 4 digits) may
be stored.

## Payment states

```text
pending, authorized, captured, failed, cancelled, partially_refunded, refunded
```

Order status and payment status are related but not the same state machine — an order doesn't
flip to "paid" from a frontend redirect alone; it requires gateway/webhook confirmation.

## Webhooks

Every webhook handler verifies signature (and timestamp, if the provider supports it), is
idempotent per event ID, and logs the raw payload after redacting sensitive values. Retries
never cause a duplicate charge or duplicate ledger entry (see `docs/SECURITY.md` §Idempotency
and `docs/DATABASE_SCHEMA.md` §Immutability).

## Idempotency

Sensitive operations (create order, accept order, create payment, refund, settlement) accept an
`Idempotency-Key` where it matters, so a client retry can never double-execute them.
