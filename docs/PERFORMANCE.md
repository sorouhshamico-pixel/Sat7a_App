# Performance & Load Hardening

## Status

Phase 24 — implemented (`apps/backend`, `apps/web`).

## Scope

`docs/MONITORING.md` defers full slow-query/N+1 monitoring tooling to Phase 25 (Production
Readiness) — this phase isn't that. It's a targeted audit of the codebase's existing query and
pagination discipline, the same "review what's already there and fix what's found" methodology
Phase 23 applied to security. Full load testing against production-scale data wasn't attempted —
this dev box's database has a handful of rows per table, so synthetic load testing here would
measure noise, not real bottlenecks; that belongs with real traffic or a dedicated staging
environment (see Not yet in this phase).

## N+1 query audit — no new findings, but the reason is now documented

Every API Resource in `app/Http/Resources/Api/V1/*.php` was checked for direct relation access.
All of them consistently use `whenLoaded()` rather than touching a relation property or method
directly — confirmed by grepping for relation-name usages, since bypassing `whenLoaded()` is the
one pattern that would actually trigger a lazy-load-per-row (a real N+1) inside a `Resource::
collection()` loop. Every controller `index()`/`show()` method was separately checked for the
matching `->with()`/`->load()` call — this is what closed the two real gaps below. The
combination (Resources never lazy-load on their own; controllers eager-load what their Resource
needs) is what keeps this codebase N+1-safe by construction, not a one-time audit result that can
silently drift — a future Resource that reads `$this->someRelation` directly instead of via
`whenLoaded()` would reintroduce the risk, so the discipline matters more than this one pass.

## Two real "missing eager-load" bugs found and fixed

Not classic N+1s (nothing scaled per-row) — a subtler, related bug class: a controller's
`index()` eager-loading *less* than its own `show()` for the identical Resource, so a field the
API's own contract promises silently came back missing only on the list screen. Both found by
comparing `index()`/`show()` pairs across every controller, then verified against a live request,
not just read from the code:

1. **`GET customers/me/orders` never eager-loaded `vehicle`.** `OrderResource`'s `vehicle` field
   (`whenLoaded('vehicle', ...)`) silently disappeared from every order on the customer's own
   order-history list, while `GET customers/me/orders/{id}` (show) always had it — confirmed live
   via curl, not assumed from the diff. No customer-facing screen happened to render the field
   yet (`src/app/(customer)/orders/page.tsx` only shows pickup/dropoff/status), so this was a
   latent API-contract gap rather than a visibly broken screen — still a real one, since this
   platform is explicitly designed API-first for a future Flutter client
   (`docs/ARCHITECTURE.md`) that would have hit it immediately. Fixed with `->with('vehicle')`
   on the list query. Regression-tested by extending the existing
   `test_a_customer_can_list_their_order_history` test in
   `tests/Feature/Api/V1/Orders/OrderCancellationTest.php`.
2. **Neither `providers/me/settlements` endpoint eager-loaded `approvedBy`.** A provider could
   never actually see who approved their own settlement batch — `SettlementBatchResource`'s
   `approved_by` field came back `null` even after a real finance officer had genuinely approved
   it. Fixed with `->with('approvedBy')` on both `index()` and `show()`. Regression-tested with a
   new `test_a_provider_sees_who_approved_their_settlement_batch` in
   `tests/Feature/Api/V1/Ledger/SettlementTest.php`, reusing that file's existing
   `setUpProviderWithSettleableEarnings` helper and walking a batch through a real
   `pending_approval → approved` transition before asserting.

## Pagination: the backend was already ready, the frontend wasn't

Five admin/provider list screens made real, unbounded-looking API calls against endpoints that
had supported `page`/`per_page` server-side since the phase that built them (confirmed by reading
each controller: `Admin\{Provider,Payment,Settlement,Dispute}Controller::index()` and
`Providers\{Settlement,Review}Controller::index()` all already call `->paginate()`) — but the UI
never sent a `page` parameter or offered any way to reach page 2, silently truncating every list
to its newest 20 rows forever. This was flagged as an explicit "Not yet in this phase" gap in
three earlier phases' own docs (`docs/OPERATIONS_COMMAND_CENTER.md`,
`docs/FINANCE_COMPLIANCE_ADMIN.md`, `docs/PROVIDER_WEB_APP.md`) — Phase 24 closes it. Extracted a
shared `src/components/pagination.tsx` (previous/next buttons, current-page/total display) and
wired it into `admin/{orders,disputes,providers,payments,settlements}` and
`provider/{settlements,reviews}` — seven list screens sharing one small component instead of
seven copies of prev/next-button logic.

Verified live, not just typechecked: since this dev database has too few real rows in any table
to naturally reach page 2, 25 synthetic provider rows were bulk-inserted directly (bypassing
domain logic entirely — this was purely to produce enough rows for the pagination *mechanism*,
not a realistic business scenario), then a real admin session clicked through to page 2 and back
via Playwright, asserting page 2's rows are genuinely different from page 1's (no overlap) and
clicking "السابق" (previous) returns the exact same page 1 content. The synthetic rows and the
admin token used to test them were deleted afterward — this dev database keeps no lasting trace
of the verification.

## A retention-hygiene job introduced its own query-cost gap — caught and fixed in the same pass

Phase 23's `data:purge-expired` command (see `docs/SECURITY_HARDENING.md`) filters
`otp_codes` by `expires_at`/`consumed_at` and `order_location_pings` by `recorded_at` alone, with
no other predicate — but neither table had an index usable for that. `otp_codes` only indexed
`(phone, user_type)`; `order_location_pings` only indexed `(order_id, recorded_at)`, which a query
with no `order_id` predicate can't use as a leading-column lookup. Both would have been full
table scans on a command that runs daily, forever, against tables that only grow. Fixed with a
migration (`2026_08_30_102420_add_retention_purge_indexes.php`) adding `otp_codes.expires_at`,
`otp_codes.consumed_at`, and `order_location_pings.recorded_at` as standalone indexes. Worth
noting as its own lesson: a security/hygiene fix's own query cost needs the same scrutiny as any
other new query, and a hardening pass on one axis (Phase 23's security work) can introduce a real
gap on another (this phase's performance concern) — checking both together in adjacent phases is
what caught it before it shipped unnoticed.

## Database index review — otherwise already solid

Spot-checked the migrations behind the highest-traffic query patterns
(`dispatch_offers`, `notifications`, `ledger_entries`) against the WHERE clauses the
corresponding controllers/actions actually issue: every one already has a composite index
matching its real query shape (`dispatch_offers(driver_id, status)`,
`notifications(user_id, read_at)` / `(user_id, created_at)`, `ledger_entries(provider_id, type,
created_at)`). No changes needed there — this project's migrations have been disciplined about
indexing from early phases, confirmed rather than assumed.

## Not yet in this phase

- Real load/stress testing against production-scale data — this dev database has single-digit to
  low-double-digit row counts per table; a synthetic load test here would measure noise. Belongs
  with a staging environment carrying realistic data volume, or Phase 25's monitoring tooling
  once real traffic exists.
- Automated N+1/slow-query detection in CI (e.g., a query-count assertion helper, or a tool like
  Laravel's `DB::listen()`-based budget check) — `docs/MONITORING.md` already frames this as
  Phase 25 scope.
- Frontend bundle-size analysis / code-splitting audit — not reviewed this phase.
- React Query cache-tuning (`staleTime`, refetch intervals) across every screen — reviewed
  opportunistically where already documented (Phase 19's order-tracking 5s polling, Phase 20's
  10s dispatch-offer polling) but not re-audited end to end this phase.
- Cursor-based pagination for the highest-growth tables (offset pagination degrades at very deep
  pages) — page-number pagination is a reasonable default at today's expected scale; revisit if a
  list genuinely needs to page past the low hundreds routinely.
