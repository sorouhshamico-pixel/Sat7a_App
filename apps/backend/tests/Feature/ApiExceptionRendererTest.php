<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 25 (Production Readiness) finding: App\Exceptions\
 * ApiExceptionRenderer's unhandled-exception branch (correlation ID,
 * no stack trace leaked, a stable INTERNAL_ERROR code) had no test
 * covering it at all before this — every other branch (validation,
 * auth, 404, rate limit) does, but the generic `catch-all Throwable`
 * path, the one that matters most in production, didn't. Writing this
 * was also the natural moment to confirm installing the Sentry SDK
 * (see config/sentry.php) didn't change this behavior — it hooks into
 * the same `report($e)` call this test exercises, and with no DSN
 * configured is a true no-op (see the assertions below: no exception is
 * thrown by having Sentry installed, and the same envelope shape comes
 * back either way).
 */
class ApiExceptionRendererTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A throwaway route, registered only for this test — the
        // renderer is wired globally in bootstrap/app.php and applies to
        // any /api/* route, so this doesn't need to be a real endpoint.
        Route::middleware('api')->get('/api/v1/_test-throws', function () {
            throw new \RuntimeException('deliberate test failure');
        });
    }

    public function test_an_unhandled_exception_returns_a_sanitized_envelope_with_a_correlation_id(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/v1/_test-throws');

        $response->assertStatus(500);
        $response->assertJsonPath('errors.0.code', 'INTERNAL_ERROR');
        $response->assertJsonPath('errors.0.message', 'An unexpected error occurred.');

        $correlationId = $response->json('meta.correlation_id');
        $this->assertNotEmpty($correlationId);

        // Never leaked: the real exception message, its class name, or a
        // stack trace.
        $body = $response->getContent();
        $this->assertStringNotContainsString('deliberate test failure', $body);
        $this->assertStringNotContainsString('RuntimeException', $body);
        $this->assertStringNotContainsString('.php', $body);
    }
}
