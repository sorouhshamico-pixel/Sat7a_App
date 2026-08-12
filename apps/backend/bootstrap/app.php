<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        $middleware->api(prepend: [
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
