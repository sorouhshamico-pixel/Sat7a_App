# Finance & Compliance Admin

## Status

Phase 18 — implemented (`apps/web/src/app/admin/(dashboard)/{providers,payments,settlements}`).

## Scope

Extends the Phase 17 Operations Command Center with the finance/compliance-side admin screens
that phase deliberately deferred: provider compliance (approve/reject/suspend, document
verification, bank account verification), payments (view + refund), and settlement batches
(generate + advance through their lifecycle). Reuses every piece of Phase 17's foundation —
the BFF auth/proxy, UI kit, TanStack Query — unchanged; this phase is new pages, not new
plumbing.

## Providers (implemented)

`GET /api/v1/admin/providers` (list, `?status=` filter) and `GET .../providers/{id}` (detail,
which also returns the provider's documents in the same response) via
`src/app/admin/(dashboard)/providers/page.tsx` and `.../providers/[id]/page.tsx`. The detail
page is the single "provider workspace" screen, combining several backend resources behind one
UI:

- **Compliance actions** — approve/reject (only offered while `pending`/`under_review`) and
  suspend (only offered while `approved`), matching `App\Domain\Providers\Enums\ProviderStatus`.
- **Documents** — each document's verify/reject actions only appear while
  `verification_status === 'pending'`.
- **Balance** (`GET .../providers/{id}/balance`) — the same three-bucket breakdown
  (`docs/SETTLEMENT_ARCHITECTURE.md`) already built for the provider's own dashboard, now visible
  to staff.
- **Bank account** (`GET .../providers/{id}/bank-account`) — a 404 here is a normal "no account
  on file yet" state, not an error page (`retry: false` on the query, handled inline). A
  `verify` action appears while unverified.
- **Generate a settlement batch** (`POST .../providers/{id}/settlements`) — a `period_start`/
  `period_end` date-range form; success links to the settlements list to track it further.
- **Reviews** — a read-only list, reusing the existing `providers.view`-gated endpoint.

## Payments (implemented)

`GET /api/v1/admin/payments` (list, `?status=` filter) and `GET .../payments/{id}` (detail +
refund history) via `src/app/admin/(dashboard)/payments/page.tsx` and `.../payments/[id]/
page.tsx`. The refund form (amount in halalas, optional reason) only appears while the
payment's status is `captured` or `partially_refunded`, mirroring
`App\Domain\Payments\Enums\PaymentStatus::isRefundable()`.

## Settlements (implemented)

`GET /api/v1/admin/settlements` (list, `?status=` filter) and `GET .../settlements/{id}`
(detail) via `src/app/admin/(dashboard)/settlements/page.tsx` and `.../settlements/[id]/
page.tsx`. The detail page's status-advance buttons are computed from
`src/lib/settlements.ts`'s `SETTLEMENT_NEXT_STATUS` map — a client-side mirror of
`App\Domain\Ledger\Enums\SettlementStatus::allowedTransitions()` — so the UI only ever offers a
legal next step (`draft → pending_approval → approved → processing → paid`, with `cancelled`/
`failed` branches). A reference field appears only when `paid` is a legal next step; a failure
reason field appears only when `failed` is.

## Real bugs found and fixed while building this (response-envelope mismatches)

Three of the new pages initially assumed a bare JSON object where the backend actually wraps the
payload one level deeper — `GetProviderBalanceAction`'s result comes back as
`{"data": {"balance": {...}}}`, not `{"data": {...}}` (matching
`App\Http\Controllers\Api\V1\Admin\LedgerController::balance()`'s
`ApiResponse::success(['balance' => ...])`); the bank account and settlement-detail endpoints
are wrapped the same way (`{"bank_account": ...}`, `{"settlement": ...}`). TypeScript's
structural typing didn't catch this — `apiGet<T>()` blindly casts the response, so the mismatch
compiled cleanly and would have rendered silent `undefined` values in production instead of
throwing. Caught only by actually logging in, creating a real provider through the API, and
reading the *rendered* page content (via a throwaway Playwright script, not by inspecting raw
JSON) — the same lesson Phase 17 already drew from its `proxy.ts` bug: a mocked-fetch unit test
proves the component logic is right, never that the assumed data shape matches the real backend.
Before trusting a new `apiGet<T>()` call site, grep the actual controller for
`ApiResponse::success([...])` and copy its exact key structure — don't assume it based on how a
sibling endpoint happens to be shaped.

## Not yet in this phase

- No document preview/download from the admin UI — `GET /api/v1/documents/{document}/download`
  exists but returns raw file bytes, not a JSON envelope, and wiring a byte-streaming Route
  Handler proxy for it wasn't done here (only metadata + verify/reject are surfaced).
- ~~No pagination controls on Providers/Payments/Settlements lists~~ — closed in Phase 24
  (`docs/PERFORMANCE.md`); this section was never updated to reflect it. All three now use the
  shared `Pagination` component.
- No dedicated reviews-moderation screen (only a read-only list embedded in provider detail).
- No dashboard-level finance metrics (total pending payouts, etc.) — the home page is still just
  navigation cards.
