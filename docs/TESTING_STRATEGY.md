# Testing Strategy

## Backend

PHPUnit (Laravel's default test runner for this project — the scaffold ships PHPUnit 12 rather
than Pest, and there's no reason to convert; both are acceptable per project conventions),
run via `php artisan test` / `composer test`. Feature tests hit real HTTP routes; the payment
gateway and SMS provider use fake adapters so no external credentials are needed to run the
suite (see `docs/PAYMENT_ARCHITECTURE.md`).

`phpunit.xml` sets `CACHE_STORE=array` for speed and isolation — but that store never actually
serializes anything, so it structurally cannot catch a bug that only exists in the real
serialize/unserialize round-trip a production cache store does (see `docs/SECURITY.md` §Cache
deserialization for a real bug this masked until Phase 17). Anything that caches an object
(never a plain array/scalar — those are always safe) needs its own test that overrides
`config(['cache.default' => 'redis'])` for that one test, the way
`tests/Unit/Domain/Authorization/CachedPermissionSetTest.php` does; don't rely on the
array-store default to exercise that path.

Required coverage as each phase lands (not exhaustive, expanded per-phase): authentication,
OTP, permissions, provider onboarding, document expiry, fleet, orders and state transitions,
pricing, dispatch, **concurrency** (two providers accepting the same order, duplicate payment
webhook, duplicate refund, duplicate order submission, duplicate settlement processing — these
are mandatory, not optional edge cases), payments, webhooks, refunds, ledger, settlements,
reviews, disputes, admin actions.

Security-specific tests (mandatory, see `docs/SECURITY.md`): a customer cannot access another
customer's order; a provider cannot access unrelated orders; a driver cannot update another
driver's trip; finance cannot modify provider compliance; support cannot change pricing; an
unauthenticated request cannot reach a private endpoint; an expired token is rejected; an
invalid webhook signature is rejected; a duplicate webhook is idempotent.

Static analysis: Larastan (PHPStan) at level 6 (`apps/backend/phpstan.neon`), style: Laravel
Pint (`apps/backend/pint.json`).

## Frontend

Vitest + React Testing Library for unit/component tests (`apps/web/vitest.config.ts`).
Playwright for end-to-end flows (`apps/web/playwright.config.ts`), covering at minimum the full
booking journey once it exists: guest estimate → authentication → order confirmation →
dispatch → provider acceptance → admin visibility → trip progression → completion → payment →
review. TypeScript strict mode (`tsc --noEmit`) and ESLint gate every phase.

## Failure-mode testing

As each integration lands, test its failure path, not just its happy path: Maps provider
unavailable, SMS provider unavailable, payment provider timeout, Redis reconnect, WebSocket
disconnect, queue retry, storage error. The system should degrade gracefully (manual dispatch
fallback, cash fallback where the business allows it, polling fallback for realtime) rather than
break outright — see `docs/DISPATCH_ENGINE.md` and `docs/ARCHITECTURE.md`.

## Gate

No phase is considered done with a failing required test, a disabled test, or a lowered static
analysis threshold used to make a phase "pass". See `docs/ROADMAP.md` §Definition of Done.
