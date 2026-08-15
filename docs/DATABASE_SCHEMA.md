# Database Schema

## Status

Conventions and cross-cutting rules, plus the tables implemented so far. This document must
stay in sync with `apps/backend/database/migrations/`.

## Tables (implemented)

- `users` — see `docs/SECURITY.md` §Authentication (Phase 1).
- `otp_codes` — see `docs/SECURITY.md` §OTP handling (Phase 1).
- `personal_access_tokens` — Sanctum (Phase 1).
- `roles`, `permissions`, `permission_role`, `role_user` — see `docs/ROLES_PERMISSIONS.md`
  (Phase 2).
- `audit_logs` — immutable, append-only (Phase 2).
- `providers` — see `docs/COMPLIANCE.md` §Provider compliance lifecycle (Phase 3).
- `documents` — polymorphic (`documentable_type`/`documentable_id`); Provider documents today,
  Driver documents from Phase 4 reuse this same table (Phase 3/4).
- `users.provider_id` — added in Phase 4; single source of truth for which provider a
  provider_staff user (owner, fleet manager, or driver) belongs to.
- `drivers` — `provider_id`, `user_id` (the driver's own login, unique), nationality/license
  fields collected only where needed, `status`, `is_available`, `rating` (Phase 4).
- `tow_trucks` — `provider_id`, `driver_id` (nullable, unique — one truck per driver),
  `service_capabilities` (JSON array), `status` (state machine, see
  `App\Domain\Fleet\Enums\TowTruckStatus`), `current_latitude`/`current_longitude`/
  `last_location_at` (plain decimals for now — see §Geography below) (Phase 4).
- `customers` — `user_id` (unique), `avatar_path`, `preferences`, `notification_preferences`
  (JSON); name/phone/email/locale/status/registration date already live on `users`, not
  duplicated here (Phase 5).
- `vehicles` — `customer_id`, make/model/year/type/color/plate_number/notes/image_path; `type`
  is free-text, not a fixed enum (Phase 5).
- `saved_locations` — `customer_id`, `label` (home/work/custom), plain lat/lng (see §Geography),
  `formatted_address`; a partial unique index enforces at most one `home` and one `work` per
  customer — `custom` is unlimited. No location *history* — only the current point per label
  (Phase 5).
- `cities` — `slug`, `name`, `name_ar`, `is_active`; Riyadh is the only active/launch city,
  five others seeded inactive so expansion (spec §152) is a data change, not a domain-logic one
  (Phase 6).

**Not yet implemented — blocked on PostGIS** (see `docs/DEPLOYMENT.md` §One-time PostGIS
setup): `service_zones` (city-scoped, center point + radius rather than a precise polygon —
no real boundary data exists to use), and a `location geography(Point,4326)` column on
`tow_trucks` for the nearby-search query. Both were written and confirmed to fail against this
database (`type "geography" does not exist`) and were deliberately held back rather than shipped
half-verified — see `docs/ROADMAP.md` Phase 6. Unlike `service_zones`, the nearby-search query
itself couldn't wait for PostGIS — Phase 9 (Dispatch) ships it via a documented temporary
Haversine query against `tow_trucks.current_latitude`/`current_longitude` instead; see
`docs/DISPATCH_ENGINE.md` for why and how it gets swapped out later.

- `pricing_rule_versions` — every pricing component (base fee, minimum fare, per-km rate,
  per-service-type fee, per-vehicle-category multiplier, night fee + window, waiting fee + free
  minutes, zone fee (reserved, 0 for now), platform service fee %, VAT %), plus `is_active`
  (exactly one at a time), `effective_from`, `created_by`, `notes`. See
  `docs/PRICING_ENGINE.md` (Phase 7).
- `orders` — `customer_id`, `vehicle_id`, `service_type`, pickup/dropoff lat+lng+formatted
  address (plain decimals, not yet PostGIS — see §Geography below), `status` (state machine, see
  `App\Domain\Orders\Enums\OrderStatus`), `pricing_snapshot` (JSON, frozen `PricingSnapshot` from
  Phase 7), `quoted_price`, `final_price` (nullable — set to the payment's amount once a payment
  captures, see `docs/PAYMENT_ARCHITECTURE.md`, Phase 12), `payment_method` (always `cash` for
  now — the actual method used for a payment attempt is `payments.method`, Phase 12),
  `assigned_provider_id`/`assigned_driver_id`/`assigned_tow_truck_id` (nullable, set by dispatch
  acceptance/manual assignment, Phase 9), `cancelled_by`/`cancellation_reason`/`cancellation_fee`, and
  a set of nullable lifecycle timestamps (`accepted_at`, `arrived_at`, `trip_started_at`,
  `completed_at`, `cancelled_at`) (Phase 8).
- `order_status_history` — append-only; `order_id`, `from_status` (nullable — null on the
  creation row), `to_status`, `changed_by` (nullable — null for system-driven transitions),
  `notes`, `created_at`. Written only by `App\Domain\Orders\Services\OrderStateMachine`, never
  directly. See `docs/ORDER_LIFECYCLE.md` (Phase 8).
- `orders.current_dispatch_wave`/`orders.manual_dispatch_required` — added in Phase 9; which
  dispatch wave last actually produced offers, and whether every configured wave was exhausted
  with no acceptance (see `docs/DISPATCH_ENGINE.md`).
- `dispatch_offers` — one row per candidate a dispatch wave offered an order to: `order_id`,
  `tow_truck_id`, `driver_id`, `provider_id`, `wave`, `distance_meters` (snapshot at offer time),
  `status` (state machine, see `App\Domain\Dispatch\Enums\DispatchOfferStatus`), `expires_at`,
  `responded_at`. Never mutated except `status`/`responded_at` on response — the offer history is
  the audit trail of who was asked and how they responded (Phase 9).
- `order_location_pings` — append-only breadcrumb trail for an order's active trip window:
  `order_id`, `latitude`/`longitude` (plain decimals — see §Geography below), `heading`/
  `speed_kmh` (both nullable), `recorded_at` (client-supplied GPS fix time, distinct from
  `created_at`, the server receipt time). Written only by
  `App\Domain\Tracking\Actions\RecordLocationPingAction`. See
  `docs/LIVE_LOCATION_TRACKING.md` (Phase 11).
- `payments` — `order_id` (not unique; an order may have more than one payment attempt over its
  life, e.g. a failed card retry), `customer_id`, `gateway`, `gateway_payment_id`, `method`
  (state machine, see `App\Domain\Payments\Enums\PaymentMethod`), `amount`, `currency`, `status`
  (state machine, see `App\Domain\Payments\Enums\PaymentStatus`), `card_brand`/`card_last_four`
  (safe metadata only — never PAN/CVV), `failure_reason`, `idempotency_key` (unique, nullable),
  and lifecycle timestamps (`authorized_at`/`captured_at`/`failed_at`/`cancelled_at`). See
  `docs/PAYMENT_ARCHITECTURE.md` (Phase 12).
- `refunds` — `payment_id`, `initiated_by` (nullable FK user), `amount`, `reason`, `status`
  (state machine, see `App\Domain\Payments\Enums\RefundStatus`), `gateway_refund_id`,
  `failure_reason`. Append-only — a captured payment can be refunded more than once (partial
  refunds), each attempt its own row (Phase 12).
- `payment_webhook_events` — idempotency ledger for inbound gateway webhooks: `gateway`,
  `event_id` (unique together), `event_type`, `payload` (JSON, redacted of sensitive values
  before storage), `processed_at`. Written only by
  `App\Domain\Payments\Actions\ProcessPaymentWebhookAction` (Phase 12).
- `ledger_entries` — append-only financial ledger: `order_id`/`payment_id`/`provider_id`
  (nullable), `settlement_batch_id` (nullable FK, the one narrow mutability exception — see
  `docs/SETTLEMENT_ARCHITECTURE.md` §Ledger), `type` (`customer_payment`/`platform_commission`/
  `gateway_fee`/`provider_payable`/`refund`/`adjustment`/`settlement`), `direction`
  (`credit`/`debit`), `amount`, `currency`, `description`. A provider's balance is derived by
  summing these, never stored as a running total. See `docs/SETTLEMENT_ARCHITECTURE.md`
  (Phase 13).
- `settlement_batches` — `provider_id`, `period_start`/`period_end` (informational labels, not a
  strict inclusion filter), `gross`/`commission`/`deductions` (unsigned, informational), `net`
  (**signed** — can be negative in principle), `status` (state machine, see
  `App\Domain\Ledger\Enums\SettlementStatus`), `approved_by` (nullable FK user), `paid_at`,
  `reference`, `failure_reason`. See `docs/SETTLEMENT_ARCHITECTURE.md` (Phase 14).
- `provider_bank_accounts` — one per provider (`provider_id` unique): `account_holder_name`,
  `iban` (encrypted at rest via Laravel's `encrypted` cast), `bank_name`, `verified`,
  `verified_by` (nullable FK user), `verified_at`. See `docs/SETTLEMENT_ARCHITECTURE.md` §Bank
  account security (Phase 14).

## Engine

PostgreSQL + PostGIS. No other database engine is used for the system of record.

## Conventions

- **Primary keys**: internal bigint auto-increment primary keys are fine for joins/FKs, but any
  entity referenced in a public URL or API response (`orders`, `providers`, `trips`, `payments`,
  `disputes`, and similar) additionally carries a `public_id` ULID column (unique, indexed) that
  is what the API exposes — never the raw integer ID. ULID, not UUIDv4, so IDs are sortable and
  index-friendly. This is not a security control by itself; every request is still authorized
  via Policies (see `docs/SECURITY.md`).
- **Money**: integer minor units (halalas) — `SAR 185.50` is stored as `18550`. Never `float` or
  `double` for anything money-related. A `currency` column (ISO 4217 code, default `SAR`)
  accompanies every money column set so multi-currency isn't a rewrite later.
- **Time**: `timestamptz` columns stored in UTC — use Laravel's `timestampTz()`, never the
  timezone-naive `timestamp()`, for **any** column populated via a DB-side default
  (`->useCurrent()`). A `timestamp()` (no tz) column stores `CURRENT_TIMESTAMP` as naive
  wall-clock text in whatever timezone the Postgres *session* happens to be configured for, so an
  affected column can silently read back shifted by that session's UTC offset (found and fixed in
  Phase 13, see `docs/SETTLEMENT_ARCHITECTURE.md` §A real bug found and fixed while building
  this, after it broke a pending-vs-available balance cutoff comparison). This risk doesn't apply
  to a column Eloquent populates from PHP (standard `$timestamps`, or an explicitly-assigned
  Carbon value) — only to `useCurrent()`/raw-SQL-default columns, since PHP-computed values are
  already correct UTC strings regardless of the DB session's timezone.

  The same session-timezone mismatch has a second, read-side form: binding a PHP `Carbon` (UTC)
  value into a `WHERE some_timestamptz_column <= ?` query gets misinterpreted by Postgres using
  its *session* timezone, since Laravel's PDO driver sends the value as a zone-less string (found
  and fixed in Phase 14, see `docs/SETTLEMENT_ARCHITECTURE.md` §A second real bug). Fixed at the
  connection level — `config/database.php`'s `pgsql` connection pins `'timezone' => 'UTC'`, which
  Laravel applies via `SET TIME ZONE 'UTC'` on every new connection — rather than per-query, so
  it protects every current and future comparison, not just one call site. Display conversion to
  `Asia/Riyadh` happens at the presentation layer, never by shifting stored values or the session
  timezone away from UTC.
- **Geography**: PostGIS `geography(Point, 4326)` for point locations (pickup, dropoff, live
  driver position, provider base), with a GiST spatial index. Radius/nearby queries use PostGIS
  functions (`ST_DWithin`, `ST_Distance`) — never manual Haversine math in PHP.
- **Enums**: PHP backed enums map to Postgres `varchar` + a `CHECK` constraint (not native
  Postgres `ENUM` types, to keep adding new states a simple migration rather than a type
  alteration). No magic strings in application code.
- **Soft deletes**: added only where a real business need exists (e.g. an order must remain
  visible in history after "cancellation", which is itself a status, not a delete). Not applied
  to every model by default.
- **Foreign keys**: always declared with an explicit `onDelete` behavior — no implicit cascade
  left to chance.
- **Indexes**: every foreign key, every column used in a `WHERE`/`ORDER BY` on a
  frequently-queried table, and composite indexes for common multi-column lookups (e.g.
  provider + status for dispatch candidate queries).

## Immutability rules

- **Pricing**: every order stores a `pricing_snapshot` (JSON) capturing the rules/rates/version
  used at quote time. Changing pricing rules later never changes historical orders (see
  `docs/PRICING_ENGINE.md`).
- **Ledger**: financial ledger entries are append-only. Corrections are new `adjustment` entries,
  never edits to historical rows (see `docs/PAYMENT_ARCHITECTURE.md` and
  `docs/SETTLEMENT_ARCHITECTURE.md`).
- **Audit log**: audit log rows are never updated or deleted by application code (see
  `docs/SECURITY.md` §Audit).

## Migrations

All schema changes go through Laravel migrations — no manual production DDL. Migrations must be
safe to run against a live database (additive where possible; destructive changes are staged
across a deprecate → backfill → drop sequence rather than a single breaking migration).
