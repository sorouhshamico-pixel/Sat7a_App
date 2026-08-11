# Riyadh Tow Platform (working name)

A production-grade marketplace platform connecting customers who need vehicle towing/recovery
services in Riyadh with licensed tow-truck providers, drivers, and fleets — built API-first so
that Flutter customer and provider mobile apps can be added later without backend rework.

This is not a prototype. It is being built in independently shippable phases, each gated by
tests, linting, static analysis, and a documentation update before moving to the next. See
[docs/ROADMAP.md](docs/ROADMAP.md) for the full phase plan and current status.

## Monorepo layout

```text
tow-platform/
├── apps/
│   ├── backend/     # Laravel API (PHP 8.3+), PostgreSQL + PostGIS, Redis, Reverb, Horizon
│   └── web/         # Next.js App Router customer/provider/admin web + PWA (TypeScript strict)
├── packages/
│   ├── api-contracts/   # OpenAPI spec + generated/shared API types
│   └── shared-config/   # Shared lint/format/tsconfig bases
├── docs/             # Living architecture & product documentation (see docs/)
├── infrastructure/   # Local dev infra (docker-compose, nginx, etc.) and deployment notes
└── .github/workflows/  # CI pipelines
```

## Stack

- **Backend**: Laravel, Sanctum, Reverb (WebSockets), Horizon (queues), Pest, Pint, Larastan.
- **Frontend**: Next.js (App Router), TypeScript strict, Tailwind CSS, TanStack Query,
  React Hook Form, Zod, Vitest, React Testing Library, Playwright.
- **Database**: PostgreSQL + PostGIS.
- **Cache/Queue/Realtime backing store**: Redis.
- **Maps**: Google Maps Platform, behind a provider-agnostic abstraction layer.
- **Architecture**: API-first modular monolith, domain-oriented, versioned REST (`/api/v1`),
  OpenAPI 3.x documented. No microservices in v1.

## Local environment (this machine)

This project targets a native Windows/Laragon development setup (no Docker available on this
host):

- PHP 8.3 (Laragon), Composer 2.9
- Node 22, npm 10
- PostgreSQL 17 + PostGIS (installed natively via winget)
- Redis (Laragon-bundled)

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for local setup steps and
[infrastructure/](infrastructure/) for connection configuration. A `docker-compose.yml` is kept
for environments where Docker is available.

## Getting started

Backend:

```bash
cd apps/backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Frontend:

```bash
cd apps/web
npm install
cp .env.example .env.local
npm run dev
```

## Documentation

Start with [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) and
[docs/PRODUCT_REQUIREMENTS.md](docs/PRODUCT_REQUIREMENTS.md). All documents in `docs/` are
expected to stay in sync with the actual codebase — update them whenever an architectural
decision changes.
