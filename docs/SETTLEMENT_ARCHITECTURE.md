# Financial Ledger, Commission & Settlement Architecture

## Status

Phase 13 (Financial Ledger & Commission) and Phase 14 (Settlement batches & bank account
security — the actual payout mechanism) are both implemented — `App\Domain\Ledger`.

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

`App\Domain\Ledger\Actions\GetProviderBalanceAction` exposes four figures:

- `total_payable` — the CURRENT amount owed to the provider right now: every balance-affecting
  entry ever recorded, netted together. A `settlement` debit (see below) already cancels out the
  batch of entries it paid off, so this correctly drops back toward `0` once a payout completes.
- `pending_balance` — earned within the last `ledger.pending_hold_hours` (default `24`,
  configurable) — a short fraud/dispute-protection window before money is considered clear.
- `available_balance` = `total_payable - pending_balance` — currently owed and outside the
  pending window. This is what a new settlement batch can actually claim and pay out.
- `settled_balance` — lifetime total already paid out via `settlement` entries. Purely
  informational; **not** subtracted a second time from `available_balance`, since the `settlement`
  debit already reduced `total_payable` (see "A second real bug" below).

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

## A second real bug found and fixed while building Phase 14

The Phase 13 fix above covered the *write* side (a `useCurrent()` default losing its zone). Phase
14's `App\Domain\Ledger\Actions\GenerateSettlementBatchAction` was the first code in the project
to filter a query by a PHP-computed cutoff *in SQL* (`WHERE created_at <= ?`) rather than fetching
rows and comparing in PHP — and that surfaced a second, distinct instance of the same underlying
misconfiguration: the local Postgres server's session timezone is `Asia/Riyadh` (`+03`), while
`config('app.timezone')` (and every Carbon value in the app) is `UTC`. Laravel's PDO Postgres
driver binds a `Carbon` value as a plain string with no UTC offset (e.g. `2026-08-16 00:00:00`);
Postgres, casting that string to `timestamptz`, interprets a zone-less string using the *session*
timezone rather than assuming UTC — silently shifting every such comparison by 3 hours. A batch's
eligibility window (`created_at <= periodEnd`, `created_at <= holdCutoff`) was therefore excluding
entries it should have claimed.

Fixed at the root — not per-query — by pinning the Postgres session timezone to `UTC` in
`config/database.php`'s `pgsql` connection (`'timezone' => 'UTC'`), which makes Laravel issue
`SET TIME ZONE 'UTC'` on every new connection. This makes the session timezone agree with
`app.timezone` for every current and future query, not just this one, and is why the Phase 13 fix
(switching columns to `timestampTz()`) was necessary but not sufficient on its own — it fixed
values *written* by the database, not values *read by* a query that binds a PHP-side Carbon
against a mismatched session timezone.

## Settlement batches (implemented)

`settlement_batches` (`App\Domain\Ledger\Models\SettlementBatch`):

```text
provider_id, period_start, period_end, gross, commission, deductions, net,
status, approved_by, paid_at, reference, failure_reason
```

`net` is **signed** (can be negative in principle, though `GenerateSettlementBatchAction` never
creates a batch with `net <= 0` — there is nothing to pay out). `gross`/`commission`/`deductions`
are informational sums derived from the same underlying payments (for display only); `net` is the
only figure that matters once the batch is `paid`.

States: `draft` → `pending_approval` → `approved` → `processing` → `paid` | `failed` |
`cancelled` (`App\Domain\Ledger\Enums\SettlementStatus`, the same
`allowedTransitions()`/`canTransitionTo()` shape as `OrderStatus`/`PaymentStatus`). The single
choke point for every transition is `App\Domain\Ledger\Actions\AdvanceSettlementStatusAction`
(mirrors `AdvanceTripStatusAction`/`PaymentStateMachine`):

- **Generation** (`App\Domain\Ledger\Actions\GenerateSettlementBatchAction`,
  `POST /api/v1/admin/providers/{provider}/settlements`): claims every currently-unclaimed
  (`settlement_batch_id IS NULL`), past-hold-window, balance-affecting ledger entry
  (`provider_payable`/`refund`/`adjustment`) dated on or before `period_end` — **no lower bound**
  on `created_at`. `period_start`/`period_end` are informational labels, not a strict inclusion
  filter: an old entry a previous batch happened to miss is always swept into the next one rather
  than silently lost. Throws `NO_ELIGIBLE_EARNINGS` if nothing qualifies or the computed `net` is
  not positive.
- **Reaching `paid`**: creates the batch's one and only `settlement`-type ledger entry — always a
  **debit** of `net` — which is what makes `total_payable`/`available_balance` correctly drop back
  toward `0` (see "Provider balance" above). Requires a *verified* bank account on file
  (`BANK_ACCOUNT_NOT_VERIFIED` / `BANK_ACCOUNT_NOT_FOUND` otherwise).
- **Reaching `failed`/`cancelled`** (`SettlementStatus::releasesClaimedEntries()`): releases every
  entry the batch had claimed back to `settlement_batch_id = null`, so a future batch can claim
  them again.

The whole lifecycle reuses the `settlements.approve` permission end to end (generate through
paid/failed/cancelled) rather than minting a permission per action, the same choice already made
for the dispatch-override workflow in Phase 9.

## Bank account security (implemented)

`provider_bank_accounts` (`App\Domain\Ledger\Models\ProviderBankAccount`), one per provider:

```text
provider_id, account_holder_name, iban, bank_name, verified, verified_by, verified_at
```

`iban` is encrypted at rest (Laravel's `encrypted` cast, same mechanism as
`User.two_factor_secret`). `ProviderBankAccountResource` returns the full value only to the
owning provider or a holder of the `settlements.view_bank_details` permission (seeded to
`finance_officer`; `admin`/`super_admin` inherit it automatically) — everyone else sees
`ProviderBankAccount::maskedIban()`, the same "sensitive field needs an extra permission beyond
general view" shape as `documents.view_sensitive`.

`App\Domain\Ledger\Actions\SetProviderBankAccountAction` (provider self-service,
`PUT /api/v1/providers/me/bank-account`) resets `verified` to `false` on **every** change,
including the first save — an edited IBAN must be re-verified before another settlement can be
marked `paid` against it. `App\Domain\Ledger\Actions\VerifyProviderBankAccountAction` (admin,
`POST /api/v1/admin/providers/{provider}/bank-account/verify`) is the only way to flip it back.
Both actions are audit-logged with the *masked* IBAN only — the raw value, even encrypted, is
never written to the audit trail.
