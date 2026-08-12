# Compliance

## Status

Implemented, Phase 3. This document is not legal advice and does not assert that any specific
regulatory rule has been implemented or verified — final legal/regulatory text and rules must be
reviewed by qualified counsel before production use.

## Provider compliance lifecycle (implemented)

```text
registration → phone verification → compliance review → approved
```

`POST /api/v1/providers/register` creates the provider_staff owner user and the `Provider` row
(status `pending`) in one transaction and sends an OTP; the applicant completes authentication
via the existing `POST /api/v1/auth/otp/verify` (Phase 1) — no changes to auth were needed,
since that endpoint already authenticates any *existing* provider_staff user (see
`docs/SECURITY.md` §Authentication). Commercial/legal details can be updated afterward via
`PATCH /api/v1/providers/me`; documents are uploaded via `POST /api/v1/providers/me/documents`.
Fleet, drivers, and bank information are added in Phase 4/14 — not yet part of the lifecycle.

A provider is never auto-activated. Statuses: `pending`, `under_review`, `approved`,
`rejected`, `suspended`, `inactive` (`App\Domain\Providers\Enums\ProviderStatus`). Every
approve/reject/suspend action (`App\Domain\Providers\Actions\ApproveProviderAction` /
`RejectProviderAction` / `SuspendProviderAction`) requires the matching permission
(`providers.approve` / `providers.suspend` — see `docs/ROLES_PERMISSIONS.md`) and is audited.
Rejection and suspension always record a reason.

## Documents (implemented)

`App\Domain\Compliance\Models\Document`, polymorphic (`documentable_type`/`documentable_id`) so
Provider documents today and Driver/Tow-Truck documents from Phase 4 share the same table and
verification workflow. Types: `commercial_registration`, `activity_license`,
`vehicle_registration`, `insurance`, `driver_license`, `identity`, `bank_proof`
(`App\Domain\Compliance\Enums\DocumentType`). Each row tracks `document_type`,
`document_number`, `issued_at`, `expires_at`, `verification_status` (`pending`/`verified`/
`rejected`), `verified_by`, `verified_at`, `rejection_reason`, `storage_path`. Storage is
private (`documents` disk, local for now — see `docs/SECURITY.md` §File uploads), never a
public URL; every download goes through `App\Http\Controllers\Api\V1\DocumentController` which
checks ownership or the `documents.view`/`documents.view_sensitive` permission fresh on every
request. `identity` documents are the one type gated behind `documents.view_sensitive` instead
of the plain `documents.view` permission, per `docs/SECURITY.md` §Data classification.
Verification (`App\Domain\Compliance\Actions\VerifyDocumentAction` / `RejectDocumentAction`)
requires `documents.verify` and is audited.

## Expiry (implemented)

`php artisan compliance:check-document-expiry`, scheduled daily at 02:00
(`routes/console.php`), scans for documents expiring in 30/15/7/1 days and already-expired
documents, logging a structured warning for each. Real notification delivery (email/SMS/in-app)
plugs into this same command once the Notifications domain lands in Phase 16 — this is the
foundation, not a placeholder to be rewritten. Whether an expired mandatory document suspends
the provider or just blocks new orders is a business decision left to a human compliance
reviewer for now, not automated.

## Legal content

Terms, Privacy, Cancellation Policy, and Provider Terms pages are placeholders until the user
supplies reviewed legal text. No legal claims are authored as if pre-approved.

## Data retention & deletion

See `docs/SECURITY.md` §Data retention. Financial records required for legal/accounting
purposes are retained even after an account deletion request, without assuming a specific
retention duration ahead of legal review.
