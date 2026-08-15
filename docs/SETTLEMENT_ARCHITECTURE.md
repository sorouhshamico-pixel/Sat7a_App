# Financial Ledger, Commission & Settlement Architecture

## Status

Phase 13 (Financial Ledger & Commission) is implemented — `App\Domain\Ledger`. Phase 14
(Settlement batches, the actual payout mechanism) is still a design sketch, recorded here since
Phase 0 so the accounting model was agreed before payments landed.

## Ledger, not a running balance (implemented)

A provider's balance is never just `sum(completed orders)`. An append-only, immutable
`ledger_entries` table (`App\Domain\Ledger\Models\LedgerEntry`) records entries of type
`customer_payment`, `platform_commission`, `gateway_fee`, `provider_payable`, `refund`,
`adjustment`, `settlement` — each with a `direction` (`credit`/`debit`) and a magnitude
(`amount`). Historical entries are never edited or deleted; a correction would be a new
`adjustment` entry (see `docs/DATABASE_SCHEMA.md` §Immutability). `LedgerEntry::signedAmount()`
is the single place the credit/debit sign convention is applied, so balance math never has to
re-derive it.

Only `provider_payable`, `refund`, `adjustment`, and `settlement` entries affect a provider's
balance (`LedgerEntryType::affectsProviderBalance()`) — `customer_payment`, `platform_commission`,
and `gateway_fee` are reporting lines that explain *how* the payable was derived, never summed
into the balance themselves.

## Commission (implemented)

`App\Domain\Ledger\Actions\RecordPaymentLedgerEntriesAction` runs once per captured payment
(triggered by `App\Domain\Payments\Events\PaymentCaptured`, see `docs/PAYMENT_ARCHITECTURE.md`).
The commission and tax figures are **not** recomputed here — they're read straight from the
order's frozen `pricing_snapshot` (`platform_service_fee`, `vat_amount`), the same immutable
snapshot `docs/PRICING_ENGINE.md` established in Phase 7, so a later pricing-rule change can
never retroactively change a historical ledger entry. `gateway_fee` is computed from
`services.payments.gateway_fee_percentage` (`0` today — no real gateway account exists, so
there's no real processing cost to model yet).

Two different formulas, depending on how the customer paid:

- **Card**: the platform received the gross amount from the gateway and owes the provider
  `gross - commission - gateway_fee - tax` — a **credit** (platform owes provider).
- **Cash**: the provider already collected the full gross amount in person — the platform
  received nothing, so the provider instead owes the platform `commission + tax` — a **debit**
  (provider owes platform), which will reduce a future payout once Phase 14 exists. This is
  deliberate, not an oversight: cash payments were folded through the same `Payment`/ledger
  system as card payments in Phase 12, so the accounting has to correctly represent that the
  money already changed hands in the wrong direction.

Refunds (`App\Domain\Ledger\Actions\RecordRefundLedgerEntryAction`, triggered by
`App\Domain\Payments\Events\RefundProcessed`) reverse the original `provider_payable` entry's
balance impact **proportionally** — a partial refund only reverses its share. This handles the
cash case correctly with no special-casing: cash's original entry is a debit, so reversing part
of it naturally produces a credit (the provider owes the platform less, since part of the
underlying sale was voided).

**Known simplification**: the formulas above assume `pricing_snapshot.discount` is `0` — true
for every order today, since no coupon system exists (`docs/PRICING_ENGINE.md`). Revisit the
discount attribution once one does.

## Provider balance (implemented)

`App\Domain\Ledger\Actions\GetProviderBalanceAction` exposes three buckets:

- `pending_balance` — earned within the last `ledger.pending_hold_hours` (default `24`,
  configurable) — a short fraud/dispute-protection window before money is considered clear.
- `available_balance` — earned outside that window, not yet settled. This is what Phase 14 will
  actually pay out.
- `settled_balance` — sum of `settlement` entries. Always `0` today, since nothing creates one
  until Phase 14 exists, but the formula is ready for when it does.

A **negative** balance is valid and expected — see the cash-payment case above, where a provider
who already collected cash directly ends up owing the platform.

`GET /api/v1/providers/me/balance` / `GET /api/v1/providers/me/ledger` (the caller's own
provider, no extra permission needed beyond ownership — same shape as every other `/me`
endpoint) and `GET /api/v1/admin/providers/{provider}/balance` /
`GET /api/v1/admin/providers/{provider}/ledger` (gated by `settlements.view`, already seeded to
`finance_officer` in Phase 2 — no permission catalog changes needed for this phase).

## A real bug found and fixed while building this

`GetProviderBalanceAction` compares each entry's `created_at` against a computed real-time
cutoff — the first piece of code in this project to actually do a time-based comparison against
a DB-populated (`useCurrent()`) timestamp rather than a PHP-computed one. That comparison
silently broke: the local Postgres server's session timezone is `Asia/Riyadh` (`+03`), and every
`created_at` column across the project using `$table->timestamp(...)->useCurrent()` (a
timezone-*naive* column) was storing `CURRENT_TIMESTAMP` as local wall-clock text with the zone
information discarded — so Laravel read every DB-defaulted timestamp back **3 hours ahead of
true UTC**. This affected six tables across five phases (`role_user.assigned_at`,
`audit_logs`, `order_status_history`, `order_location_pings`, `payment_webhook_events`, and this
phase's `ledger_entries`) — invisible until now because nothing had previously compared one of
these DB-defaulted timestamps against a real-time cutoff computed in PHP.

Fixed by switching every affected column to `timestampTz()` (`timestamp with time zone`) instead
of `timestamp()` — this makes Postgres store and return the value correctly regardless of the
session's timezone setting, a self-contained fix that doesn't depend on server configuration
being correct (see `docs/DATABASE_SCHEMA.md` §Time). The local dev database was rebuilt
(`migrate:fresh`) to pick up the corrected column types, since it held no data worth preserving.

## Settlement batches (design — Phase 14)

```text
provider_id, period_start, period_end, gross, commission, deductions, net,
status, approved_by, paid_at, reference
```

States: `draft`, `pending_approval`, `approved`, `processing`, `paid`, `failed`, `cancelled`.

## Bank account security (design — Phase 14)

IBAN is masked in general UI; full value requires an elevated permission. Any change to a
provider's bank account is audit-logged and subject to additional verification (see
`docs/SECURITY.md`, `docs/ROLES_PERMISSIONS.md`).
