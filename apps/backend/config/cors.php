<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | In this project's architecture the browser never calls this API
    | directly — every request goes through the Next.js app's own
    | server-side route handlers (see apps/web/src/app/api/backend/
    | [...path]/route.ts and docs/OPERATIONS_COMMAND_CENTER.md), which
    | attach the Sanctum Bearer token server-side. This means a browser
    | CORS preflight against /api/* is not part of this API's real traffic
    | pattern today — but it wasn't previously locked down at all (no
    | config/cors.php existed and Illuminate\Http\Middleware\HandleCors
    | wasn't registered), which is a real, if currently low-severity,
    | Phase 23 security-hardening finding (see docs/SECURITY.md §CORS):
    | any future code path that DID call this API straight from browser
    | JS would have had no explicit origin restriction to rely on. This
    | file closes that gap with a narrow, single-origin allowlist rather
    | than leaving it silently open-ended.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // No cookie-based Sanctum SPA auth here (see docs/SECURITY.md
    // §Authentication) — the browser never sends credentials to this API,
    // so there's nothing for a credentialed CORS response to protect.
    'supports_credentials' => false,

];
