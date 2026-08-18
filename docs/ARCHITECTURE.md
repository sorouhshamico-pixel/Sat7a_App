# Architecture

## Status

Phase 0 (Foundation) — this document reflects decisions locked in during Phase 0 and will be
updated as later phases add dispatch, pricing, payments, and realtime subsystems.

## 1. System shape

API-first modular monolith. One Laravel application exposes a versioned REST API
(`/api/v1/...`) consumed today by a Next.js web/PWA client and, in the future, by two
independent Flutter apps (Customer, Provider/Driver) without any backend changes beyond
additive API versions.

```text
Next.js (web + PWA)  ──┐
                        ├──►  /api/v1  (Laravel, Sanctum-authenticated)
Flutter Customer app ──┤            │
Flutter Provider app ──┘            ├── PostgreSQL + PostGIS (system of record)
                                     ├── Redis (cache, queues, locks, presence)
                                     ├── Horizon (queue worker supervision)
                                     ├── Reverb (WebSocket realtime channel)
                                     └── Private S3-compatible object storage (documents, media)
```

No microservices, no event sourcing, no Kubernetes/Kafka in v1. These are explicitly out of
scope until a proven need emerges (see `docs/ROADMAP.md`).

## 2. Backend domain organization

Business logic lives in `app/Domain/<DomainName>`, not in controllers. Controllers are thin:
they validate (Form Requests), authorize (Policies), delegate to a Service/Action class, and
shape the response. Planned domains:

```text
app/Domain/
├── Authentication/   Users/          Customers/     Providers/
├── Drivers/          Fleet/          Orders/        Dispatch/
├── Pricing/          Trips/          Payments/      Settlements/
├── Reviews/          Disputes/       Compliance/    Notifications/
├── Audit/            Admin/
```

Each domain groups its own Models, Actions/Services, Policies, Events, Listeners, and Enums.
Cross-domain communication goes through domain services or domain events, not direct model
reach-through, so domains stay independently testable.

## 3. Data layer

- **PostgreSQL + PostGIS** is the single system of record. PostGIS `geography`/`geometry`
  columns and spatial indexes back all "nearby provider" / service-zone queries — no manual
  Haversine math in PHP.
- **Money** is stored as integer minor units (halalas), never float. See `docs/DATABASE_SCHEMA.md`.
- **IDs**: public-facing entities (orders, providers, trips, payments, disputes) use ULIDs in
  URLs/API responses instead of auto-increment integers. This is not a security control by
  itself — every request is still authorized via Policies.
- **Money/pricing** is immutable per-order via `pricing_snapshot` — historical orders never
  change when pricing rules change later (see `docs/PRICING_ENGINE.md`).

## 4. Realtime

Implemented, Phase 10. Laravel Reverb provides authenticated, per-order/per-driver WebSocket
channels — order status changes and new dispatch offers broadcast automatically. Every channel
subscription is authorized server-side (`routes/channels.php`); no client can subscribe to an
order or driver channel it doesn't own or isn't assigned to. See `docs/REALTIME.md` for the full
design and `docs/DISPATCH_ENGINE.md` for the dispatch-offer side.

## 5. Background processing

Redis-backed queues, supervised by Horizon. Long-running or external-facing work (dispatch
waves, notifications, webhooks, settlement generation, document-expiry scans) always runs in
jobs, never inline in an HTTP request. Sensitive jobs (payments, settlements) are designed to
be idempotent so retries can't double-charge or double-pay.

## 6. Maps

All mapping/geocoding/routing calls go through a provider-agnostic interface
(`GeocodingProvider`, `RoutingProvider`, `PlacesProvider`) with a `GoogleMapsProvider`
implementation. Nearby-provider search always starts from a PostGIS radius query; only the
short-listed candidates are sent to a routing API for real ETAs, to control cost. See
`docs/DISPATCH_ENGINE.md`.

## 7. Payments

Payments are behind a `PaymentGatewayInterface` with a fake/test adapter used in development
and CI, and real gateway adapters (e.g. Moyasar/Tap/HyperPay) added when credentials exist.
No card data is ever stored server-side — see `docs/PAYMENT_ARCHITECTURE.md`.

## 8. Frontend

Next.js App Router, TypeScript strict mode, Arabic RTL as the primary locale (`dir="rtl"
lang="ar"`) with English as a future secondary locale. Server state via TanStack Query, forms
via React Hook Form + Zod, UI primitives via Tailwind + shadcn/ui-style components defined
once in a shared design system (`docs/` design tokens documented alongside components).

The admin surface (Phase 17, `docs/OPERATIONS_COMMAND_CENTER.md`) never calls the Laravel API
directly from a client component — every request goes through a same-origin Next.js Route
Handler proxy that attaches the session token server-side from an httpOnly cookie, so the
Sanctum token never reaches browser JavaScript. This BFF pattern is the template for every
future authenticated surface in this app (customer/provider web, Phases 19-20), not something
specific to the admin screens.

## 9. Environments

`local`, `testing`, `staging`, `production`. Local development on this project currently runs
natively (PHP/Laragon + native PostgreSQL/PostGIS + Laragon Redis) rather than Docker, because
Docker was not available on the initial development machine; `docker-compose.yml` is still
maintained for environments where Docker is available (CI, other contributors, staging).

## 10. Explicitly deferred / not built in v1

Microservices, Kubernetes, Kafka, full event sourcing, complex CQRS, blockchain, AI-based
pricing/support. See `docs/ROADMAP.md` §Future Features for the deferred list and the
architectural hooks left for them (feature flags, city/service-zone modeling instead of a
hardcoded "Riyadh", currency stored as a code not hardcoded "SAR", etc.).
