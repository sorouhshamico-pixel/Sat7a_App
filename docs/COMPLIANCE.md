# Compliance

## Status

Design document. Provider document verification and expiry mechanics are implemented in
Phase 3 (Provider Onboarding); this document is not legal advice and does not assert that any
specific regulatory rule has been implemented or verified — final legal/regulatory text and
rules must be reviewed by qualified counsel before production use.

## Provider compliance lifecycle

```text
registration → phone verification → provider details → commercial/legal information →
documents → fleet → drivers → bank information → compliance review → approved
```

A provider is never auto-activated. Statuses: `pending`, `under_review`, `approved`,
`rejected`, `suspended`, `inactive`. Rejection and suspension always record a reason.

## Documents

Commercial documents, activity license, vehicle registration, insurance, driver license,
identity documents (where legally required), bank proof. Each document tracks
`document_type`, `document_number`, `issued_at`, `expires_at`, `verification_status`,
`verified_by`, `verified_at`, `rejection_reason`, `storage_path`. Storage is private, never a
public URL (see `docs/SECURITY.md` §File uploads).

## Expiry

A daily scheduled job scans expiry dates and sends alerts at configurable thresholds (e.g. 30/
15/7/1 days). Business rules (documented, not invented ad hoc) decide whether an expired
mandatory document suspends the provider or just blocks new orders.

## Legal content

Terms, Privacy, Cancellation Policy, and Provider Terms pages are placeholders until the user
supplies reviewed legal text. No legal claims are authored as if pre-approved.

## Data retention & deletion

See `docs/SECURITY.md` §Data retention. Financial records required for legal/accounting
purposes are retained even after an account deletion request, without assuming a specific
retention duration ahead of legal review.
