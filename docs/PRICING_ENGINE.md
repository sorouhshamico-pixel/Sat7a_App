# Pricing Engine

## Status

Design document for Phase 7 (Pricing Engine). Recorded here in Phase 0 so Orders (Phase 8) and
Dispatch (Phase 9) are designed against an agreed contract (`pricing_snapshot`) from the start.

## Components (subject to refinement in Phase 7)

```text
base_fee, minimum_fare, distance_rate, service_type_fee, vehicle_multiplier,
tow_type_multiplier, night_fee, waiting_fee, zone_fee, special_condition_fee,
platform_service_fee, discount, coupon, VAT
```

## Rules

- Production pricing is never hardcoded in application code. Pricing rules are data
  (database-backed), editable only by a permission (`pricing.update`), and every change is
  audit-logged (see `docs/ROLES_PERMISSIONS.md`, `docs/SECURITY.md`).
- Every order stores a `pricing_snapshot` — the exact rules/rate version/distance/fees/discount/
  tax/total used at quote time — so historical orders never change when pricing rules change
  later (see `docs/DATABASE_SCHEMA.md`).
- Price types: `fixed_quote`, `estimated_range`, `manual_quote`. Some situations (severely
  damaged vehicle, no wheels, underground parking, recovery operations, unusual vehicle,
  special loading) can't be priced automatically and are routed to `manual_quote_required` —
  execution is blocked until the customer accepts the manual price.
- Money is integer minor units (halalas), never float (see `docs/DATABASE_SCHEMA.md`).
- VAT/tax logic is an abstraction, not hardcoded into UI strings — the actual rate/rules require
  review before production (see `docs/COMPLIANCE.md`).
