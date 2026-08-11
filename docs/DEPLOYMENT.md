# Deployment

## Local development (this machine — native Windows/Laragon)

Docker was not available on the initial development machine, so local dev runs natively rather
than via `docker-compose`. This is a deliberate per-environment choice, not a project-wide
requirement — `infrastructure/docker-compose.yml` is still maintained for any environment where
Docker is available (CI, other contributors, staging).

Prerequisites installed on this machine:

- PHP 8.3 + Composer (via Laragon)
- Node 22 + npm
- PostgreSQL 17 (installed via `winget install --id PostgreSQL.PostgreSQL.17`)
- PostGIS 3.6 (bundle merged into the PostgreSQL install — see below)
- Redis (Laragon-bundled, `C:\laragon\bin\redis\redis-x64-5.0.14.1`)

### One-time PostGIS setup

The PostGIS bundle has to be merged into `C:\Program Files\PostgreSQL\17\`, which requires
administrator rights this project's automation doesn't have. Run once, in an **elevated**
PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File infrastructure\install-postgis-windows.ps1
```

This copies the PostGIS 3.6.2 bundle files into the PostgreSQL 17 install directory, creates the
`tow_platform` database, and runs `CREATE EXTENSION postgis;`.

### Starting services

```bash
# Redis (Laragon-bundled)
"C:\laragon\bin\redis\redis-x64-5.0.14.1\redis-server.exe" "C:\laragon\bin\redis\redis-x64-5.0.14.1\redis.windows.conf"

# Backend
cd apps/backend
composer install
php artisan migrate
php artisan serve          # http://localhost:8000
php artisan queue:work     # background jobs (see Horizon note below)
php artisan reverb:start   # websocket server

# Frontend
cd apps/web
npm install
npm run dev                # http://localhost:3000
```

### Horizon on Windows

Laravel Horizon requires the `pcntl` and `posix` PHP extensions, which **do not exist** for
Windows PHP builds (not just disabled — never compiled). `laravel/horizon` is installed and
declared correctly in `composer.json` for Linux staging/production (Composer's
`platform-check: false` in `apps/backend/composer.json` lets `composer install` succeed locally
despite the missing extensions), but `php artisan horizon` will not run on this machine. Local
dev and CI use `php artisan queue:work` against the Redis queue instead; Horizon supervises the
same queue in staging/production (Linux).

## Environments

`local`, `testing`, `staging`, `production`. Never use production credentials locally.

## Environment variables

See `apps/backend/.env.example` and `apps/web/.env.example` (frontend copy added when Phase 6+
introduces client-side config) — variable names only, no real secrets. Real values live in each
environment's own `.env`, never committed.

## Production readiness

Not evaluated until Phase 25. At minimum before any real deploy: `APP_DEBUG=false`, HTTPS
enforced, security headers verified, rate limits verified, Horizon supervised by
systemd/supervisor on a Linux host, Reverb running behind a WebSocket-capable reverse proxy,
queue workers supervised with restart policies, scheduled backups with a tested restore
procedure (see `docs/MONITORING.md`).
