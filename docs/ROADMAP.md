# Roadmap

The platform is built in independently shippable phases. Each phase is gated by: tests passing,
lint passing, static analysis passing, frontend typecheck/build passing, a security-impact
review, and a documentation update — before moving to the next phase (see the Definition of
Done below). Nothing is skipped or bypassed to "make a phase pass."

## Status

| Phase | Name | Status |
|---|---|---|
| 0 | Architecture & Foundation | Done |
| 1 | Authentication & Security Foundation | Done |
| 2 | Roles & Permissions | Done |
| 3 | Provider Onboarding | Done |
| 4 | Fleet & Drivers | Done |
| 5 | Customer Profiles & Vehicles | Done |
| 6 | Maps & Location Foundation | Partially done — see notes |
| 7 | Pricing Engine | Done |
| 8 | Orders | Done |
| 9 | Dispatch & Matching | Done |
| 10 | Realtime | Done |
| 11 | Live Location Tracking | Done |
| 12 | Payments | Done |
| 13 | Financial Ledger & Commission | Done |
| 14 | Settlements | Done |
| 15 | Reviews & Disputes | Done |
| 16 | Notifications | Done |
| 17 | Operations Command Center | **Done** |
| 18 | Finance & Compliance Admin | Not started |
| 19 | Customer Web App Polish | Not started |
| 20 | Provider Web / PWA | Not started |
| 21 | PWA | Not started |
| 22 | Marketing & SEO | Not started |
| 23 | Security Hardening | Not started |
| 24 | Performance & Load Hardening | Not started |
| 25 | Production Readiness | Not started |

## Phase 0 — Architecture & Foundation (this phase)

Scope: monorepo skeleton, Laravel backend, Next.js frontend, PostgreSQL + PostGIS, Redis,
environment configuration, CI foundation, documentation, coding standards, health endpoints,
base test infrastructure.

Definition of done for Phase 0 specifically:
- Backend runs (`php artisan serve`).
- Frontend runs (`npm run dev`) and builds (`npm run build`).
- PostgreSQL connection works; PostGIS extension enabled.
- Redis works (cache/session/queue/broadcasting all point at it).
- Backend tests run (`php artisan test` / Pint / Larastan).
- Frontend tests run (Vitest, typecheck, ESLint).
- CI pipeline passes on push.
- Documentation created (this set of files).
- No secrets committed (`.env` is gitignored, `.env.example` has no real values).

Status: done, except PostGIS extension activation, which needs a one-time admin-elevated step
on this Windows dev machine (`infrastructure/install-postgis-windows.ps1`, documented in
`docs/DEPLOYMENT.md`) that hasn't been run yet. Nothing built so far depends on PostGIS —
it's needed starting Phase 6.

## Phase 1 — Authentication & Security Foundation (this phase)

Implemented: customer/provider-staff phone + OTP login (`app/Domain/Authentication/`), admin
email + password + mandatory TOTP MFA with recovery codes, session/device management (list,
revoke one, revoke all), the `/api/v1` response envelope wired into a global exception renderer
(`app/Exceptions/ApiExceptionRenderer.php`) so every error — validation, auth, not-found, rate
limit, unexpected — comes back in the standard shape with a stable `ErrorCode`, baseline
security headers, and named rate limiters for OTP send/verify and admin login (see
`docs/SECURITY.md` and `docs/API_SPECIFICATION.md` for details).

Not yet in this phase: roles/permissions enforcement (that's Phase 2), provider-staff account
provisioning (Phase 3/4 — for now, provider-staff OTP login only works for accounts seeded
directly, since there's no onboarding flow yet).

Testing note: the test suite runs against a real local PostgreSQL database
(`tow_platform_testing`), not SQLite, because migrations use Postgres-specific syntax (CHECK
constraints now, PostGIS geography columns from Phase 6 on) that SQLite can't run. CI
provisions the same database as a service container.

## Phase 2 — Roles & Permissions (this phase)

Implemented: `roles`/`permissions`/`permission_role`/`role_user` tables, seeded via
`database/seeders/RolePermissionSeeder.php`, a `Gate::before` hook
(`app/Providers/AuthorizationServiceProvider.php`) that turns every permission slug into a
usable Laravel authorization ability, admin role-management endpoints (list roles, assign/revoke
a user's role) gated by the `roles.manage` permission, an immutable audit log
(`App\Domain\Audit\Services\AuditLogger`) wired into role assignment/revocation, and an
`admin:create-super-admin` artisan command to bootstrap the first admin account (there's no
public admin registration endpoint). See `docs/ROLES_PERMISSIONS.md` for the full catalog.

Not yet in this phase: provider-scoped role enforcement (a fleet manager restricted to *their*
provider) — that needs the Provider domain from Phase 3/4; Policy classes for domain models,
since no domain models with ownership exist yet beyond User/Role/Permission themselves.

## Phase 3 — Provider Onboarding (this phase)

Implemented: `providers` and (polymorphic) `documents` tables; provider registration
(`POST /api/v1/providers/register`) that creates the provider_staff owner user, the pending
`Provider` row, and the `provider_owner` role assignment in one transaction, then sends an OTP —
the applicant completes authentication via the existing Phase 1 OTP-verify endpoint, so
Phase 1 auth code needed zero changes. Provider profile view/update
(`GET`/`PATCH /api/v1/providers/me`); private document upload/list/download
(`/api/v1/providers/me/documents`, `/api/v1/documents/{document}/download`) with content-based
MIME validation, size limits, and double-extension rejection. Admin compliance endpoints under
`/api/v1/admin/providers` and `/api/v1/admin/documents` (list/filter, approve, reject, suspend,
verify, reject), every mutating action gated by a permission and audited. A daily scheduled
command (`compliance:check-document-expiry`) alerts on documents expiring in 30/15/7/1 days or
already expired. See `docs/COMPLIANCE.md` for details.

Not yet in this phase: fleet/drivers (Phase 4, which will also let a provider_staff user who
isn't the owner — a fleet manager or driver — resolve "their" provider); bank accounts
(Phase 14); real notification delivery for expiry alerts (Phase 16, logged for now).

## Phase 4 — Fleet & Drivers (this phase)

Implemented: `drivers` and `tow_trucks` tables, plus a `users.provider_id` column added to
unify "which provider does this provider_staff user belong to" across owner, fleet manager, and
driver alike — Phase 3's provider controllers were refactored to resolve through it too
(`App\Http\Controllers\Concerns\ResolvesProvider`) instead of the narrower owner-only lookup.
Adding a driver follows the same shape as provider registration: creates the driver's
(unverified) provider_staff login and the `Driver` profile in one transaction, then sends an
OTP — the driver completes authentication via the existing Phase 1 OTP-verify endpoint.

Tow trucks have a real state machine (`App\Domain\Fleet\Enums\TowTruckStatus`) — every
transition is validated against an explicit matrix; the provider-facing status endpoint only
reaches the offline/available/maintenance/unavailable subgraph, since the dispatch-driven states
(reserved/en_route/arrived/loading/on_trip) are set by the dispatch/trip system from Phase 9
onward, through the same action and therefore the same matrix. `service_capabilities` is a
JSON array of an enum (`App\Domain\Fleet\Enums\ServiceCapability`) so a truck can support more
than one. A driver can only be assigned to one truck at a time (enforced at the DB level and in
`AssignDriverToTowTruckAction`).

Admin-side driver/tow-truck suspension deliberately uses **separate** permissions
(`drivers.suspend`/`fleet.suspend`) from the provider-side self-service ones
(`drivers.manage`/`fleet.manage`) — sharing one permission across both would have let a
provider_owner reach the platform-wide admin suspend endpoint for *any* provider's driver, not
just their own, since a permission check alone doesn't scope by provider the way the `/me`
routes structurally do. Caught and fixed during Phase 4 rather than shipped (see
`docs/ROLES_PERMISSIONS.md`).

Not yet in this phase: PostGIS geography for `current_latitude`/`current_longitude` (plain
decimal columns for now — converted once spatial "nearby" queries land in Phase 6); dispatch
integration for the reserved/en_route/etc. states (Phase 9); bank accounts (Phase 14).

## Phase 5 — Customer Profiles & Vehicles (this phase)

Implemented: a `customers` table holding what's genuinely customer-domain-specific (avatar,
`preferences`, `notification_preferences`) — name/phone/email/locale/status/registration date
already live on `users` from Phase 1, so they aren't duplicated. `App\Domain\Authentication\Actions\VerifyOtpAction`
(Phase 1) now also creates the `Customer` profile row, with default notification preferences,
the moment a brand-new customer auto-provisions on first OTP login — no separate "complete your
profile" step is required before the account is usable.

Saved vehicles (`vehicles`) and saved locations (`saved_locations`) are both simple,
customer-scoped CRUD resources under `/api/v1/customers/me/...`, resolved via
`App\Http\Controllers\Concerns\ResolvesCustomer` (mirrors `ResolvesProvider` from Phase 3/4) —
never a `{customer}` route parameter. A customer can only ever have one saved "home" and one
"work" location (checked in `AddSavedLocationAction`, backed by a Postgres partial unique
index as the final guard) but unlimited "custom" ones; no location *history* is retained, only
the current saved point per label (see docs/SECURITY.md §Data retention). Vehicle `type` is a
free-text field rather than a fixed enum — categories vary too widely to enumerate usefully.
Avatar/vehicle photos go through the public disk (not the private `documents` machinery from
Phase 3, since they aren't compliance-sensitive) via a small shared `StorePublicImageAction`.

Not yet in this phase: any admin-side customer management UI (not part of this phase's scope);
location autocomplete/geocoding (Phase 6, Maps & Location Foundation).

## Phase 6 — Maps & Location Foundation (this phase, partially done)

**Implemented and verified:** the map provider abstraction
(`App\Domain\Maps\Contracts\{GeocodingProvider,PlacesProvider,RoutingProvider}`) with a complete
Google Maps adapter (unexercised without a real key, but ready — see below) and a deterministic
fake adapter, bound automatically based on whether `GOOGLE_MAPS_API_KEY` is set
(`App\Providers\MapsServiceProvider`) — the exact same "interface + real adapter + fake
fallback" pattern as SMS (Phase 1) and payments (planned for Phase 12). Public, rate-limited
endpoints (`/api/v1/maps/geocode`, `/reverse-geocode`, `/places/autocomplete`, `/places/{id}`,
`/route`) so the API key never reaches the client and a guest can build a quote before
authenticating. A `cities` table/seeder (Riyadh active, five other cities seeded inactive) so
domain logic never hardcodes "Riyadh" (spec §152), with a public `/api/v1/cities` endpoint.

**Blocked, not implemented in this pass:** PostGIS-backed service zones and the "nearby tow
truck" spatial search — the actual PostGIS extension still isn't installed on this dev machine
(pending since Phase 0's one-time admin-elevation step; see `docs/DEPLOYMENT.md`). Two
migrations (`service_zones`, and a `location geography` column on `tow_trucks`) were written,
tested against a live `CREATE TABLE ... geography(...)` statement, and confirmed to fail with
`SQLSTATE[42704]: type "geography" does not exist` — exactly the expected error. Since this
project's test suite runs against a real Postgres database and refreshes the full schema before
every test class, leaving those migrations in place would have broken every previously-passing
test locally, not just new Phase 6 ones — so they were held back out of this commit entirely
rather than shipped half-verified. They'll be reintroduced once PostGIS is active, most likely
as part of Phase 9 (Dispatch), which is the actual consumer of nearby-search. CI's Postgres
service container already runs the `postgis/postgis` image, so once these are re-added they'll
be verified there regardless of local machine state.

Not yet in this phase: real Google API credentials (code is complete and ready — see
`App\Domain\Maps\Adapters\Google\*` — but untested against the live API since no key exists;
see docs/SECURITY.md §Secrets); PostGIS setup on this machine (`docs/DEPLOYMENT.md`).

## Phase 7 — Pricing Engine (this phase)

Implemented: a versioned rate card (`pricing_rule_versions`) — every component from spec §44
(base fee, minimum fare, per-km distance rate, per-service-type fee, per-vehicle-category
multiplier, night fee with a configurable window, per-minute waiting fee with free minutes, a
reserved zone fee, a platform service fee percentage, VAT percentage) lives in the database,
never hardcoded, editable only via `pricing.update` (granted to admin/super_admin only) and
always audited. Exactly one version is active at a time; creating a version never activates it —
activation is a separate, audited step, so a draft rate card can be reviewed before going live.

`App\Domain\Pricing\Actions\GenerateQuoteAction` computes the full breakdown and returns a
`PricingSnapshot` — the same shape an Order will store verbatim once Phase 8 exists, so
historical orders won't move when rates change later (see docs/DATABASE_SCHEMA.md
§Immutability). The public `POST /api/v1/pricing/quote` endpoint lets a guest get a price before
authenticating; a `requires_manual_quote` flag (for the situations spec §48 lists — severely
damaged vehicle, no wheels, underground parking, ...) skips the calculator entirely and returns
`price_type: manual_quote` with no computed total, rather than showing an automated price for a
case that doesn't suit one.

`vehicle_category` is a small, fixed pricing-only classification
(`App\Domain\Pricing\Enums\VehicleCategory`), deliberately separate from the customer-facing
free-text `vehicles.type` field from Phase 5 — one is a display field, the other a fee-multiplier
lookup key, and conflating them would have made either the customer-facing field oddly
restrictive or the pricing lookup unreliable.

Not yet in this phase: coupons/discounts (deferred — spec explicitly lists promo codes/referrals
as a future feature; the `discount` field exists in every snapshot and is always 0 for now, so
adding a coupon system later doesn't change the snapshot shape); zone-based pricing (the
`zone_fee` column exists and is always 0 — depends on Phase 6's deferred PostGIS service zones);
VAT rate/rules need legal/tax review before production (see docs/COMPLIANCE.md) — the 15%
default is Saudi Arabia's standard rate but is not asserted here as legally reviewed.

## Phase 8 — Orders (this phase)

Implemented: `orders` and `order_status_history` tables, a real state machine
(`App\Domain\Orders\Enums\OrderStatus`) covering the full booking → trip → cancellation/refund
lifecycle, and `App\Domain\Orders\Services\OrderStateMachine` as the single place every status
change goes through — validated against the matrix and recorded atomically in the same DB
transaction as the status update. `App\Domain\Orders\Actions\CreateOrderAction` never trusts a
client-supplied price or distance: it always recomputes the route via the Phase 6 `RoutingProvider`
and the price via the Phase 7 `GenerateQuoteAction`, storing the resulting `PricingSnapshot`
verbatim. Customer-facing endpoints (`/api/v1/customers/me/orders/...`) follow the same
`ResolvesCustomer`-scoped, no-bare-route-parameter pattern as Phase 5's vehicles/saved-locations;
admin endpoints (`/api/v1/admin/orders`) reuse the already-seeded `orders.view_all`/`orders.cancel`
permissions from Phase 2. Domain events (`OrderCreated`, `OrderCancelled`) are dispatched by the
actions and logged structurally by `App\Domain\Orders\Listeners\LogOrderLifecycleListener`,
standing in for real notifications until Phase 16. See `docs/ORDER_LIFECYCLE.md` for the full
state diagram and rules.

Not yet in this phase: dispatch/matching — an order sits in `pending` with no automated
transition out of it until Phase 9 exists; payments — `payment_method` is always `cash` and
`final_price` is always `null` until Phase 12; a manual-quote order workflow — a
`requires_manual_quote` order is rejected at creation with `MANUAL_QUOTE_REQUIRED` rather than
routed to a staff queue that doesn't exist yet; order photo upload — noted as a reasonable
near-term follow-up but out of this phase's core scope.

## Phase 9 — Dispatch & Matching (this phase)

Implemented: automated order-to-provider matching. `App\Domain\Dispatch\Actions\DispatchOrderAction`
runs right after order creation (`App\Domain\Dispatch\Listeners\StartDispatchListener`, subscribed
to `OrderCreated` — a dispatch failure is logged and never breaks order creation itself), finds
eligible nearby tow trucks, and offers the order to each of them as a `dispatch_offers` row. A
wave that finds no candidates widens automatically (configurable radius/candidate-count per wave
in `config/dispatch.php`) until one succeeds or every configured wave is exhausted, at which point
`orders.manual_dispatch_required` is flagged for an operations dispatcher. Driver-facing endpoints
(`/api/v1/drivers/me/dispatch-offers/...`) let a driver view, accept, or reject an offer; accepting
is concurrency-safe (row-locked DB transaction, re-verified after the lock, every other pending
offer for the order marked `superseded`) so two drivers can never both be assigned the same order.
A driver rejecting the last pending offer in a wave escalates immediately; an offer nobody
responds to is expired and escalated by a scheduled command
(`dispatch:escalate-stale-offers`, every minute). Operations staff can manually assign a specific
eligible truck or retry automated dispatch (`/api/v1/admin/orders/{order}/dispatch/...`, gated by
the `orders.assign` permission already seeded in Phase 2, and audited). See
`docs/DISPATCH_ENGINE.md` for the full design and — importantly — the PostGIS trade-off this
phase made.

**The PostGIS trade-off**: this project's own conventions say nearby-location queries must use
PostGIS, never manual Haversine math in PHP (`docs/DATABASE_SCHEMA.md` §Geography). PostGIS is
still not installed locally, and `tow_trucks` still has no geography column either (both deferred
in Phase 6). Phase 6 could defer its PostGIS-dependent pieces without losing much — Phase 9
couldn't, since a dispatch engine with no candidate search isn't a dispatch engine. Asked how to
proceed given this exact trade-off, the decision made was to implement the full engine now behind
a swappable interface (`App\Domain\Dispatch\Contracts\NearbyTowTruckFinder`), with a documented,
temporary Haversine implementation as the only adapter
(`App\Domain\Dispatch\Adapters\Haversine\HaversineNearbyTowTruckFinder`) rather than block this
phase indefinitely or ship an unverifiable PostGIS adapter no local environment could run. See
`docs/DEPLOYMENT.md` §One-time PostGIS setup for what unblocks the real implementation.

Not yet in this phase: real PostGIS-backed candidate search (see above); the full weighted
scoring design (distance/rating/acceptance-rate/cancellation-rate/response-time) — today's
ranking is distance-only, since acceptance-rate/cancellation-rate/response-time data doesn't
exist anywhere yet (no dispatch history existed before this phase); a driving-route ETA call to
the Maps `RoutingProvider` for short-listed candidates (candidates are ranked by straight-line
distance only for now); an "override eligibility" manual-assignment variant (today's manual
assignment still enforces normal eligibility checks); real-time push of new offers to drivers
(Phase 10, Realtime — a driver has to poll `GET .../dispatch-offers` today).

## Phase 10 — Realtime (this phase)

Implemented: Laravel Reverb wired up for real, authenticated per-order/per-driver WebSocket
channels — closing the gap Phase 9 explicitly left open. `App\Domain\Orders\Events\OrderStatusChanged`
broadcasts on `orders.{orderPublicId}` from the single choke point every order status change
already goes through (`OrderStateMachine::transition()`), so cancellation, dispatch acceptance,
manual assignment, and every later phase's transitions broadcast with no per-caller wiring.
`App\Domain\Dispatch\Events\DispatchOfferCreated` broadcasts on `drivers.{driverPublicId}` the
moment `DispatchOrderAction` creates an offer, so a driver's app can show a new job without
polling. Both events implement `Illuminate\Contracts\Events\ShouldDispatchAfterCommit`, since the
actions dispatching them routinely run inside their own (sometimes nested) DB transaction — this
defers the actual broadcast until the outermost commit, so nothing broadcasts for a change that
then rolls back.

`POST /api/v1/broadcasting/auth` requires the same fully-privileged Sanctum token as the rest of
the API — registered explicitly via `->withBroadcasting()` in `bootstrap/app.php` rather than the
`channels:` shorthand on `withRouting()`, whose default middleware (the session-based `web`
guard) a Bearer-token API client can't satisfy. Channel authorization
(`routes/channels.php`) is tested against the real Pusher-protocol-compatible driver, not the
test suite's default `null` broadcaster, which doesn't implement authorization logic at all. See
`docs/REALTIME.md` for the full design, including a documented testing-environment gotcha around
`Broadcast::channel()` registering onto whichever driver instance is current at the moment
`routes/channels.php` is `require`d.

Not yet in this phase: no frontend (Echo/Reverb JS client) consumes any of this — that's Phase
19+; no presence channels; no live-location broadcast (Phase 11 will very likely reuse the
`orders.{orderPublicId}` channel for a new event type once trip tracking exists).

## Phase 11 — Live Location Tracking (this phase)

Implemented: two things that only make sense together. First, a driver-facing trip-status
endpoint (`POST /api/v1/drivers/me/orders/{orderPublicId}/status`,
`App\Domain\Orders\Actions\AdvanceTripStatusAction`) — genuinely missing until now, meaning no
order could ever progress past `provider_assigned` or reach a terminal state. It exposes exactly
the seven-status forward chain (`provider_en_route` through `completed`), validated both by an
explicit whitelist and by the state machine's own adjacency check, and mirrors the assigned tow
truck's status forward in step — including, critically, `on_trip → available` on `completed`, a
real gap left open since Phase 9 (nothing ever returned a truck to service after a trip). The
same gap existed for cancellation (`CancelOrderAction` never freed a reserved truck); fixed in
the same pass. Second, live GPS reporting (`POST /api/v1/drivers/me/location`,
`App\Domain\Tracking\Actions\RecordLocationPingAction`) — keeps `tow_trucks.current_latitude`/
`current_longitude` fresh (no real update path existed before this phase, despite Phase 9's
dispatch search depending on that data), and, while the driver has an order in an active trip
status, records an append-only breadcrumb and broadcasts it live on the same
`orders.{orderPublicId}` channel Phase 10 introduced — reused exactly as that phase's docs
anticipated. `GET .../orders/{order}/location` (customer and admin) returns the current position
plus a bounded recent path, falling back to the tow truck's last known position before any trip
ping has arrived. See `docs/LIVE_LOCATION_TRACKING.md` for full details.

Not yet in this phase: no map visualization (frontend, Phase 19+); no geofencing (arrival is
driver-reported, not auto-detected); no distance/duration computation from the breadcrumb trail;
no driver-initiated cancellation endpoint (the `OrderCancelledBy::Provider` enum case has existed
since Phase 8 but nothing exposes it); still no PostGIS — `order_location_pings` uses the same
plain-decimal trade-off as `tow_trucks.current_latitude`/`current_longitude` from Phase 9, with
no immediate pressure to convert since nothing here does a spatial query.

## Phase 12 — Payments (this phase)

Implemented: `App\Domain\Payments` — a gateway abstraction
(`App\Domain\Payments\Contracts\PaymentGateway`) with a fake adapter as the only implementation
(no real gateway account exists yet, matching the SMS/Maps provider pattern), a `PaymentStatus`
state machine mirroring `OrderStatus`'s design exactly, and the customer/admin/webhook endpoints
that create, confirm, and refund payments. `App\Domain\Payments\Services\PaymentStateMachine` is
the single choke point every payment status change goes through — used identically by a
synchronous cash capture (`CreatePaymentAction`) and an async card confirmation
(`ProcessPaymentWebhookAction`) — which updates `orders.final_price` on capture and broadcasts
`PaymentCaptured`/`PaymentFailed` on the existing `orders.{orderPublicId}` channel (Phase 10).
Webhook handling verifies a real HMAC signature (even against the fake gateway — not a stub that
always passes), is idempotent per `(gateway, event_id)`, and never fails loudly for a stale/
unknown event, per `docs/PAYMENT_ARCHITECTURE.md` §Webhooks. Payment creation supports an
`Idempotency-Key` header so a client retry can never double-charge. A payment can only be
created once an order reaches `vehicle_delivered`/`completed`, matching the product requirement
("Trip completes; customer pays") literally. Refunds (full or partial, tracked separately in
`refunds`) are admin/finance-initiated only and always audited; `payments.view`/`payments.refund`
were already seeded to `finance_officer` back in Phase 2, so no permission catalog changes were
needed.

**Scope note, deliberate**: the Phase 0 design sketch listed a separate `authorize` method on the
gateway interface; it was dropped once the phase actually landed, since nothing in this product
needs a hold-then-capture-later flow — the same kind of refinement Phase 8 made when it dropped
the speculative `draft`/`quote_ready` order states.

Not yet in this phase: a real gateway adapter (no credentials exist); commission/ledger
recording (landed in Phase 13, immediately after this one); settlement payouts (Phase 14 — a
provider's balance is trackable from Phase 13 on, but nothing pays it out yet); automatic
refund-on-cancellation (no cancellation-fee policy has ever been defined, so a human decides for
now); a reconciliation job polling stuck-`pending` payments (the `getPaymentStatus()` interface
method exists but nothing calls it on a schedule).

## Phase 13 — Financial Ledger & Commission (this phase)

Implemented: `App\Domain\Ledger` — an append-only, immutable financial ledger
(`ledger_entries`) that a provider's balance is *derived from*, never stored as a running total.
`App\Domain\Ledger\Actions\RecordPaymentLedgerEntriesAction` runs on every captured payment
(`PaymentCaptured`, Phase 12), reading the commission/tax figures straight from the order's
already-frozen `pricing_snapshot` (Phase 7) rather than recomputing them — a later pricing
change can never retroactively touch a historical ledger entry. Card and cash payments use
different formulas: a card payment credits the provider `gross - commission - gateway_fee - tax`
(the platform owes them); a cash payment — money the provider already collected in person,
never touching the platform — debits them `commission + tax` (they owe the platform), correctly
modeling that Phase 12 folded cash through the same payment/ledger system as card. Refunds
reverse the original entry's balance impact proportionally, which handles partial refunds and
the cash-debit case correctly with no special-casing.

`App\Domain\Ledger\Actions\GetProviderBalanceAction` exposes `pending_balance` (earned within a
configurable hold window, default 24h — a fraud/dispute-protection buffer),
`available_balance` (outside the window, not yet settled — what Phase 14 will actually pay
out), and `settled_balance` (always `0` today, formula ready for when Phase 14 exists). A
negative balance is valid and expected (the cash-debit case). `GET /api/v1/providers/me/balance`
and `.../ledger` (own provider) and the admin equivalents (gated by `settlements.view`, already
seeded to `finance_officer` in Phase 2 — no permission catalog changes needed) expose this.

**A real, previously-invisible bug was found and fixed while building this**: comparing a
DB-populated (`useCurrent()`) timestamp against a real-time cutoff exposed that the local
Postgres server's session timezone (`Asia/Riyadh`, `+03`) had been silently corrupting every
timezone-naive `timestamp()` column populated by a DB-side default across six tables and five
phases (`role_user`, `audit_logs`, `order_status_history`, `order_location_pings`,
`payment_webhook_events`, and this phase's `ledger_entries`) — each read back 3 hours ahead of
true UTC. Fixed by switching every affected column to `timestampTz()`, a self-contained fix
that doesn't depend on the DB server being configured correctly. See
`docs/SETTLEMENT_ARCHITECTURE.md` and `docs/DATABASE_SCHEMA.md` §Time for the full account.

Not yet in this phase: settlement batches / actual payouts (Phase 14); bank account
storage/masking (Phase 14); anything that creates a `settlement`-type ledger entry.

## Phase 14 — Settlements (this phase)

Implemented: `settlement_batches` and `provider_bank_accounts`
(`App\Domain\Ledger\Models\SettlementBatch`/`ProviderBankAccount`), plus the actions that make
Phase 13's ledger actually payable out.

`App\Domain\Ledger\Actions\GenerateSettlementBatchAction`
(`POST /api/v1/admin/providers/{provider}/settlements`) claims every currently-unclaimed,
past-hold-window, balance-affecting ledger entry dated on or before the batch's `period_end` —
deliberately **no lower bound** on `created_at`, so an entry a previous batch missed is always
swept into the next one rather than permanently stuck. `App\Domain\Ledger\Actions\
AdvanceSettlementStatusAction` is the single choke point for every status transition (mirrors
`AdvanceTripStatusAction`/`PaymentStateMachine`): `draft` → `pending_approval` → `approved` →
`processing` → `paid` | `failed` | `cancelled`
(`App\Domain\Ledger\Enums\SettlementStatus`). Reaching `paid` requires a *verified* bank account
and creates the batch's one `settlement`-type ledger entry (a debit of `net`) — the entry that
finally makes `GetProviderBalanceAction`'s `settled_balance` non-zero and drops
`available_balance` back toward `0`. Reaching `failed`/`cancelled` releases every entry the batch
had claimed back to unclaimed. The whole lifecycle — generate through paid/failed/cancelled —
reuses the `settlements.approve` permission end to end, the same "one permission for a whole
admin workflow" choice already made for dispatch overrides in Phase 9.

Bank accounts (`App\Domain\Ledger\Actions\SetProviderBankAccountAction`/
`VerifyProviderBankAccountAction`): IBAN is encrypted at rest and masked in every response except
to the owning provider or a holder of the new `settlements.view_bank_details` permission (seeded
to `finance_officer`) — the same "extra permission for a highly-sensitive field" shape as
`documents.view_sensitive`. Any change to the account resets `verified` to `false`, requiring
re-verification before another settlement can be marked `paid` against it. Both actions are
audit-logged with the masked IBAN only — the raw value is never written to the audit trail.

**A second real, previously-invisible timezone bug was found and fixed while building this** —
distinct from Phase 13's. `GenerateSettlementBatchAction` was the first code in the project to
filter a query by a PHP-computed cutoff *in SQL* rather than fetching rows and comparing in PHP,
and that exposed that the local Postgres server's session timezone (`Asia/Riyadh`) still didn't
match `config('app.timezone')` (`UTC`) even after Phase 13's column-type fix — Laravel binds a
`Carbon` value as a zone-less string, and Postgres interprets it using the *session* timezone
rather than assuming UTC, silently shifting comparisons by 3 hours. Fixed at the connection level
this time — `config/database.php`'s `pgsql` connection now pins `'timezone' => 'UTC'` — so every
current and future query is protected, not just this call site. See
`docs/SETTLEMENT_ARCHITECTURE.md` §A second real bug and `docs/DATABASE_SCHEMA.md` §Time.

A smaller bug in `GetProviderBalanceAction`'s formula was also caught and fixed here, before any
real `settlement` entry had ever existed to exercise it: `available_balance` was computed as
`total_payable - pending - settled`, double-subtracting the already-paid amount (since the
`settlement` debit had already reduced `total_payable`). Fixed to `total_payable - pending`, with
`settled_balance` reported as a positive lifetime-paid figure rather than fed back into the
subtraction.

Not yet in this phase: an automated settlement-generation schedule (batches are generated
on-demand by finance staff, not on a cron); an actual bank-transfer/payout gateway integration
(marking `paid` records the ledger entry and a free-text `reference`, but nothing calls out to a
real payment rail).

## Phase 15 — Reviews & Disputes (this phase)

Implemented: `App\Domain\Reviews` and `App\Domain\Disputes`, filling in the two domain folders
`docs/ARCHITECTURE.md` reserved for them since Phase 0.

Reviews (`reviews` table, one per order): a customer rates their own order once it reaches
`completed` (`App\Domain\Reviews\Actions\CreateReviewAction`,
`POST /api/v1/customers/me/orders/{orderPublicId}/review`). Every new review recalculates the
provider's and driver's cached `rating` — a simple average, the same stored-aggregate shape
`drivers.rating` already had from Phase 4 (`providers.rating` is new this phase). Admin visibility
into a provider's reviews reuses `providers.view` rather than a new permission, since it's
read-only information about an existing resource.

Disputes (`disputes` table): a customer disputes their own order once it reaches a terminal state
(`completed` or any `cancelled_*`) via `App\Domain\Disputes\Actions\RaiseDisputeAction`. States —
`open` → `under_review` → `resolved`/`rejected` — deliberately allow no shortcut past
`under_review`, so a staff member always "picks up" a dispute (recording `assigned_to`) before
closing it, and closing one requires non-empty resolution notes. The single choke point for every
transition is `App\Domain\Disputes\Actions\AdvanceDisputeStatusAction`, audit-logged like every
other sensitive staff decision in this project. Unlike reviews, disputes got two brand-new
permissions — `disputes.view`/`disputes.manage` — seeded to `customer_support` and
`operations_manager`, since this is a genuinely new sensitive workflow rather than read access to
an existing one; the whole lifecycle reuses `disputes.manage` end to end, the same "one
permission per admin workflow" choice made for settlements in Phase 14 and dispatch overrides in
Phase 9.

Not yet in this phase: editing/deleting a review; a provider-side response to a review or
dispute; any automatic action tied to a dispute's resolution (a refund, if warranted, is still a
separate manual step via the existing payments-refund endpoint); public-facing provider ratings
(there is no public "browse providers" endpoint — dispatch is automatic).

## Phase 16 — Notifications (this phase)

Implemented: `App\Domain\Notifications`, filling in the domain folder reserved since Phase 0 and
closing the two explicitly-deferred gaps left by earlier phases — order lifecycle notifications
(`docs/ORDER_LIFECYCLE.md`) and document expiry alerts (`docs/COMPLIANCE.md` §Expiry).

`App\Domain\Notifications\Actions\SendNotificationAction` is the single entry point: it always
persists an in-app inbox record (`notifications` table), then best-effort fans out to whichever
external channels (`sms`/`push`/`email`/`whatsapp`) the recipient has enabled per
`customers.notification_preferences` (Phase 5) — a recipient with no customer profile (a provider
owner, driver, or staff member) gets the same defaults, since preferences are customer-only for
now. External delivery is fire-and-forget: a failed channel is logged, never allowed to block the
in-app record or the business action that triggered it. Every channel follows the same
interface-not-vendor-SDK pattern Phase 1 already established for SMS/OTP — `SmsProvider` (reused),
plus new `PushProvider`/`EmailProvider`/`WhatsappProvider` contracts, all defaulting to a `Log*`
adapter since no real vendor has credentials for any of them yet.

Wired into two event sources: `App\Domain\Notifications\Listeners\SendOrderNotificationListener`
(order created, cancelled, and milestone status changes — assigned/en-route/arrived/completed,
not every granular trip state) and `App\Domain\Compliance` document expiry scan (now notifies the
document's owner, not just a structured log line). A single shared inbox API
(`GET /api/v1/notifications/me`, `POST /api/v1/notifications/me/{id}/read`) serves every
authenticated user type identically, ownership-scoped to the caller.

**A pre-existing gap surfaced and fixed while wiring this up**: `App\Domain\Orders\Events\
OrderCancelled` didn't implement `ShouldDispatchAfterCommit`, even though it's dispatched from
inside `CancelOrderAction`'s own DB transaction — harmless while its only listener was a
side-effect-free log line, but a real bug waiting to happen once a listener with actual side
effects (this phase's notification send) was attached. Fixed by adding the interface, matching
every other transactionally-dispatched event in the Payments/Ledger domains.

Not yet in this phase: real vendor credentials for any channel; per-provider/per-driver
notification preferences (customer-only, as before); a "mark all read" endpoint; notifications for
reviews, disputes, settlements, or payments (bounded to the two explicitly-deferred integration
points from earlier phases — wiring up more event sources going forward is a small, mechanical
addition, not a redesign).

## Phase 17 — Operations Command Center (this phase)

Implemented: the first real UI in `apps/web` — everything before this phase was backend-only.
See `docs/OPERATIONS_COMMAND_CENTER.md` for the full write-up; summary here.

Admin login mirrors the backend's mandatory two-step flow (password, then MFA setup-or-challenge
— `docs/SECURITY.md` §Authentication) via a cookie-based BFF: Next.js Route Handlers
(`src/app/api/auth/...`) hold the short-lived MFA token only in the login page's own React
state, and write the real Sanctum session to an httpOnly cookie only once MFA succeeds
(`src/lib/session.ts`). Every authenticated admin screen calls a single generic proxy
(`src/app/api/backend/[...path]/route.ts`) that attaches that cookie's token server-side — the
session token never reaches client-side JavaScript. Built: admin Orders (list/detail, dispatch
retry/manual-assign/cancel) and Disputes (list/detail/advance-status) against the existing
Phase 8/9/15 admin API endpoints, a small hand-rolled Tailwind UI kit, and TanStack Query for
all server state.

**Two real bugs found while testing this phase against a live backend, neither introduced by
it**:

1. `App\Domain\Authorization\Concerns\HasRoles::cachedPermissionSet()` (which backs
   `Gate::before`, run on nearly every authenticated request) cached `Illuminate\Support\
   Collection` objects. Under `CACHE_STORE=array` (the whole PHPUnit suite) this looked fine;
   under `CACHE_STORE=redis` (local dev's `.env`, presumably production) every request past the
   first cache write came back `__PHP_Incomplete_Class`, because Laravel's secure-by-default
   `serializable_classes = false` blocks unserializing any object out of the cache. Fixed by
   caching plain arrays instead — see `docs/SECURITY.md` §Cache deserialization. This was a
   pre-existing, cross-cutting bug affecting every phase's authorization checks, not specific to
   this one; fixed and shipped as its own commit before continuing this phase's frontend work.
2. Next.js 16 renamed `middleware.ts` to `proxy.ts` (silently ignoring the old filename), and
   with this app's `src/` layout the file must additionally live at `src/proxy.ts`, not the
   project root. Getting either wrong means every `/admin/*` route renders with **no auth gate
   at all** — caught only by a Playwright e2e test actually navigating to a protected route
   unauthenticated, not by any vitest/component test, since those mock `fetch()` and never
   exercise real routing.

Not yet in this phase: handling an expired/revoked session mid-use (only the initial page load
is gated); tow-truck search/autocomplete for manual dispatch (a raw ID text field, since no
admin "list tow trucks" endpoint exists); realtime (WebSocket/Reverb) updates on any screen;
dashboard metrics beyond two static links; permission-based UI hiding (the backend's own checks
are the real boundary — a denied action surfaces its 403 inline); Finance & Compliance screens
(Phase 18's scope).

## Definition of Done for every phase

1. Feature implemented.
2. Tests added.
3. Existing tests pass.
4. Lint passes.
5. Static analysis passes (Larastan/PHPStan).
6. TypeScript passes.
7. Frontend tests pass.
8. Production build passes.
9. Security impact reviewed.
10. Documentation updated.
11. Git diff reviewed.
12. Commit created with a Conventional Commit message.
13. Push completed if a remote exists and credentials permit it.
14. Working tree clean.

## Explicitly deferred (not built until a proven need exists)

Driver bidding, dynamic pricing, wallet, subscriptions, corporate accounts, insurance
integrations, intercity transport, loyalty, promo codes, referrals, call masking, AI support,
ML-based pricing, Kubernetes, Kafka, microservices, full event sourcing, complex CQRS,
blockchain. Architectural hooks are left for the ones that are cheap to leave open (feature
flags, city/service-zone modeling instead of hardcoded "Riyadh", currency as a code rather than
a hardcoded "SAR" string) — see `docs/ARCHITECTURE.md` §10.

## Future Saudi expansion & mobile

Geographic modeling uses `cities` / `service_zones` rather than hardcoding Riyadh in domain
logic, so Jeddah, Dammam, Makkah, Madinah, and Taif can be added later without a rewrite. All
business logic lives server-side in the Laravel API so Flutter Customer and Provider/Driver
apps can be built later against the same `/api/v1` surface without backend rework (see
`docs/API_SPECIFICATION.md` and `docs/ARCHITECTURE.md`).
