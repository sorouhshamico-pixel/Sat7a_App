<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Registered separately from `channels:` above (rather than letting
    // withRouting wire it) so /broadcasting/auth requires the same
    // fully-privileged Sanctum token as the rest of the API, under the
    // same /api/v1 prefix — the framework default is the session-based
    // `web` guard, which a Bearer-token API client can't satisfy (see
    // docs/ARCHITECTURE.md §4).
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api/v1', 'middleware' => ['auth:sanctum', 'abilities:*', 'throttle:api']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // A real Phase 25 (Production Readiness) finding: this was never
        // configured at all. Without it, Laravel trusts none of a reverse
        // proxy's `X-Forwarded-*` headers — a real, deployable-today bug,
        // not a hypothetical one, given docs/DEPLOYMENT.md already
        // describes deploying behind one (Reverb's WebSocket-capable
        // reverse proxy). Two concrete things silently break without
        // this: (1) `$request->isSecure()` always returns false behind a
        // TLS-terminating proxy, so SecurityHeaders' HSTS header —
        // "implemented since Phase 0" — would never actually fire in the
        // realistic deployment topology this project's own docs
        // describe; (2) `$request->ip()` returns the proxy's IP for
        // every request, collapsing every per-IP rate limiter (otp-send,
        // admin-login's IP bucket, location-ping/payment-create's
        // unauthenticated fallback — see docs/SECURITY.md's rate-limit
        // table) onto one shared bucket for every real user behind that
        // proxy — one abusive client could rate-limit-lock out everyone.
        // TRUSTED_PROXIES is env-configurable (never hardcoded, matching
        // docs/SECURITY.md's retention-config precedent) — set to the
        // load balancer/reverse proxy's actual IP/CIDR in production, or
        // "*" only when fronted by an edge you don't control a fixed IP
        // for (a managed platform's own edge network). Empty by default
        // so local dev (no proxy in front) is unaffected.
        $middleware->trustProxies(at: array_filter(explode(',', (string) env('TRUSTED_PROXIES', ''))) ?: null);

        $middleware->api(prepend: [
            // See config/cors.php and docs/SECURITY.md §CORS — a Phase 23
            // hardening fix; wasn't registered at all before this.
            HandleCors::class,
            SecurityHeaders::class,
        ]);

        $middleware->throttleApi();

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        ApiExceptionRenderer::register($exceptions);
    })->create();
