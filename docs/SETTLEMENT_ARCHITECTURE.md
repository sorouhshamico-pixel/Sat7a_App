# Settlement Architecture

## Status

Design document for Phase 13 (Financial Ledger & Commission) and Phase 14 (Settlements).
Recorded here in Phase 0 so the accounting model is agreed before payments land.

## Ledger, not a running balance

Provider balance is never just `sum(completed orders)`. An append-only, immutable financial
ledger records entries such as `customer_payment`, `platform_commission`, `provider_payable`,
`gateway_fee`, `refund`, `adjustment`, `settlement`. Historical entries are never edited;
corrections are new `adjustment` entries (see `docs/DATABASE_SCHEMA.md` §Immutability).

## Commission

Per completed order: `gross_amount`, `platform_commission`, `tax`, `payment_gateway_fee`,
`provider_payable`, `discount` are all computed and recorded — accounting values are kept
separate from whatever simplified figure the UI chooses to display.

## Provider balance

Exposed as `pending_balance`, `available_balance`, `settled_balance`, with a clearly defined
rule for when a pending amount becomes available (finalized in Phase 13/14).

## Settlement batches

```text
provider_id, period_start, period_end, gross, commission, deductions, net,
status, approved_by, paid_at, reference
```

States: `draft`, `pending_approval`, `approved`, `processing`, `paid`, `failed`, `cancelled`.

## Bank account security

IBAN is masked in general UI; full value requires an elevated permission. Any change to a
provider's bank account is audit-logged and subject to additional verification (see
`docs/SECURITY.md`, `docs/ROLES_PERMISSIONS.md`).
