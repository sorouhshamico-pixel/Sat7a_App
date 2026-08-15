# Reviews & Disputes

## Status

Phase 15 — implemented (`App\Domain\Reviews`, `App\Domain\Disputes`).

## Reviews

One review per order (`reviews.order_id` unique), left by the customer via
`POST /api/v1/customers/me/orders/{orderPublicId}/review`
(`App\Domain\Reviews\Actions\CreateReviewAction`). Only allowed once the order has reached
`completed` (`ORDER_NOT_REVIEWABLE` otherwise); a second attempt on the same order fails with
`REVIEW_ALREADY_EXISTS`. `provider_id`/`driver_id` are copied off the order at write time rather
than joined through it on every read.

Every new review recalculates both the provider's and (if the order had an assigned driver) the
driver's cached `rating` column — a simple average across every review on record, mirroring the
`drivers.rating` column's existing shape from Phase 4 (a stored aggregate, not computed on every
read, since it's read far more often than it changes). `providers.rating` is the same shape,
added this phase.

No dedicated permission was minted for reading reviews: `GET /api/v1/providers/me/reviews`
(provider self-service, ownership-scoped) needs none, and `GET
/api/v1/admin/providers/{provider}/reviews` reuses `providers.view` — this is read-only
information *about* a provider, not a distinct workflow.

## Disputes

A customer raises a dispute on their own order via
`POST /api/v1/customers/me/orders/{orderPublicId}/dispute`
(`App\Domain\Disputes\Actions\RaiseDisputeAction`), with a `reason`
(`overcharge`/`service_quality`/`damage`/`no_show`/`other`) and free-text `description`. Only
allowed once the order has reached a terminal state — `completed` or any `cancelled_*`
(`ORDER_NOT_DISPUTABLE` otherwise, since there's nothing to dispute about a trip still in
progress). At most one non-terminal (`open`/`under_review`) dispute may exist per order at a time
(`DISPUTE_ALREADY_OPEN`) — a resolved/rejected dispute doesn't block raising a new one later, so
the table is not unique on `order_id`, only application-checked.

States: `open` → `under_review` → `resolved` | `rejected`
(`App\Domain\Disputes\Enums\DisputeStatus`). Deliberately narrow: `open` only ever advances to
`under_review` — there is no shortcut straight to a terminal state, so a member of staff must
explicitly "pick up" a dispute (recording `assigned_to`) before resolving or rejecting it.
Resolving or rejecting requires non-empty `resolution_notes`
(`DISPUTE_RESOLUTION_NOTES_REQUIRED` otherwise) and records `resolved_by`/`resolved_at`. The
single choke point for every transition is `App\Domain\Disputes\Actions\AdvanceDisputeStatusAction`
(mirrors `AdvanceSettlementStatusAction`/`AdvanceTripStatusAction`), and every transition is
audit-logged — a dispute outcome is a sensitive staff decision, the same bar
`docs/SECURITY.md` §Audited actions applies to refunds and settlements.

Unlike reviews, disputes get their own dedicated permissions — `disputes.view` (list/view any
dispute) and `disputes.manage` (advance a dispute's status, covering the whole
open→under_review→resolved/rejected lifecycle, the same "one permission for a whole admin
workflow" choice already made for dispatch overrides in Phase 9 and the settlement lifecycle in
Phase 14) — seeded to `customer_support` and `operations_manager`
(`docs/PRODUCT_REQUIREMENTS.md`: "Customer Support — handles tickets and disputes"). This is a
genuinely new, sensitive workflow, not read-only information about an existing resource, which is
why it didn't reuse an existing permission the way review-viewing did.

## Not yet in this phase

- No customer-facing "edit/delete a review" — a review is a one-time, immutable record of the
  order it belongs to.
- No provider-side response/rebuttal to a review or dispute.
- No automatic action tied to a dispute's resolution (e.g. auto-triggering a refund) — a
  `resolved` dispute records free-text `resolution_notes` only; if a refund is warranted, staff
  issue it separately via the existing `POST /api/v1/admin/payments/{payment}/refund`
  (`docs/PAYMENT_ARCHITECTURE.md`).
- No public-facing provider rating display — there is no public "browse providers" endpoint yet
  (dispatch is automatic; a customer never picks a provider), so `providers.rating` is only
  surfaced to the provider themselves and to staff.
