<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Phase 23 security-hardening fix — see docs/SECURITY.md §CORS. Before
 * this, no config/cors.php existed and Illuminate\Http\Middleware\
 * HandleCors wasn't registered at all, so /api/* carried no explicit
 * CORS policy. Verified live against a running dev server before writing
 * this regression test (curl OPTIONS/GET with several Origin headers) —
 * confirmed Laravel's CORS middleware always echoes back the single
 * *configured* allowed_origins entry, not the request's actual Origin
 * header, which is safe: a browser's same-origin check compares the
 * response's Access-Control-Allow-Origin against its OWN origin, not
 * against what the server received, so a mismatched value here still
 * blocks a disallowed origin from reading the response client-side.
 */
class CorsTest extends TestCase
{
    public function test_preflight_request_gets_a_cors_response_without_reaching_auth(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/admin/orders', server: [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        // No auth token was sent — if CORS didn't short-circuit before the
        // auth middleware, this would be a 401, not a clean preflight
        // response.
        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    public function test_a_real_request_carries_the_configured_allow_origin_header(): void
    {
        $response = $this->call('GET', '/api/v1/health', server: [
            'HTTP_ORIGIN' => 'http://localhost:3000',
        ]);

        $response->assertOk();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }
}
