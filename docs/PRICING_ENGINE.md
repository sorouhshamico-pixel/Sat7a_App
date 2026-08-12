# Pricing Engine

## Status

Implemented, Phase 7. `pricing_rule_versions` (`database/migrations/2026_08_12_103112_*`),
`App\Domain\Pricing\Models\PricingRuleVersion`, `App\Domain\Pricing\Actions\GenerateQuoteAction`.
Orders (Phase 8) will store the `PricingSnapshot` this produces verbatim; Dispatch (Phase 9)
will pass in the distance/service-type/vehicle-category once an order exists.

## Components (implemented)

```text
base_fee, minimum_fare, distance_rate_per_km, service_type_fees (per
App\Domain\Fleet\Enums\ServiceCapability), vehicle_category_multipliers (per
App\Domain\Pricing\Enums\VehicleCategory), night_fee (+ configurable night_start_hour/
night_end_hour), waiting_fee_per_minute (+ free_waiting_minutes grace period), zone_fee
(reserved, 0 until Phase 6's deferred PostGIS zones exist), special_condition_fee,
platform_service_fee_percentage, vat_percentage, discount (always 0 — no coupon system yet)
```

`tow_type_multiplier` and `vehicle_multiplier` from the original spec list are the same concept
in this implementation — `vehicle_category_multipliers`, keyed by a small fixed pricing
classification (`VehicleCategory`), deliberately separate from the customer-facing free-text
`vehicles.type` field (Phase 5).

## Calculation order (`GenerateQuoteAction`)

```text
subtotal_before_multiplier = base_fee + distance_fee + service_type_fee
                              + night_fee + waiting_fee + zone_fee + special_condition_fee
subtotal_before_platform_fee = max(subtotal_before_multiplier * vehicle_category_multiplier,
                                    minimum_fare)
subtotal = subtotal_before_platform_fee + (subtotal_before_platform_fee * platform_service_fee_percentage)
taxable_amount = subtotal - discount
total = taxable_amount + (taxable_amount * vat_percentage)
```

## Rules (implemented)

- Production pricing is never hardcoded in application code. Every rate lives in
  `pricing_rule_versions`, editable only by `pricing.update` (granted to `admin`/`super_admin`
  only in the seeded catalog — see `docs/ROLES_PERMISSIONS.md`), and every create/activate is
  audit-logged (`App\Domain\Audit\Services\AuditLogger`).
- Exactly one version is `is_active` at a time. Creating a version never activates it —
  activation (`App\Domain\Pricing\Actions\ActivatePricingRuleVersionAction`) is a separate,
  audited step, so a draft rate card can be reviewed before going live.
- `GenerateQuoteAction` returns a `PricingSnapshot` — the exact rule-version id/label, distance,
  every fee component, discount, tax, and total used at quote time. Orders (Phase 8) will store
  this verbatim, so historical orders never change when pricing rules change later (see
  `docs/DATABASE_SCHEMA.md` §Immutability).
- Price types: `fixed_quote` (implemented), `manual_quote` (implemented — a
  `requires_manual_quote` flag on the quote request skips the calculator entirely and returns no
  computed price), `estimated_range` (not implemented — not needed yet; `fixed_quote` covers the
  MVP per spec §47).
- Manual quote situations (severely damaged vehicle, no wheels, underground parking, recovery
  operations, unusual vehicle, special loading) are flagged by the caller (customer/UI), not
  detected automatically — there's no vehicle-damage classifier. Order *execution* being blocked
  until the customer accepts a manually-set price is an Order-domain rule for Phase 8, not
  something the pricing engine itself enforces (it has no concept of an order yet).
- Money is integer minor units (halalas), never float (see `docs/DATABASE_SCHEMA.md`).
- VAT/tax logic is a configurable percentage on the rate-card version, not hardcoded into UI
  strings — the actual rate/rules require legal/tax review before production (see
  `docs/COMPLIANCE.md`); the seeded default (15%) is Saudi Arabia's standard VAT rate but is not
  asserted here as legally reviewed for this platform.
- No coupon/discount system yet (deferred — see `docs/ROADMAP.md` §Explicitly deferred). The
  `discount` field exists in every snapshot and is always 0, so adding one later doesn't change
  the snapshot shape orders already have.

## Public quote endpoint (implemented)

`POST /api/v1/pricing/quote` — no authentication required (a guest builds a quote before
logging in, per `docs/PRODUCT_REQUIREMENTS.md`), rate-limited (`docs/SECURITY.md` §Rate
limiting). Accepts `distance_meters`, `service_type`, `vehicle_category`, optional
`waiting_minutes`, optional `requires_manual_quote`. Distance is a pre-computed input — Pricing
stays decoupled from Maps; Order creation (Phase 8) is what will call the Maps routing endpoint
first and pass the resulting distance in here.
