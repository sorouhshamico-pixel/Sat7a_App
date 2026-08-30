<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Phase 25 (Production Readiness) finding — see bootstrap/app.php and
 * docs/SECURITY.md §HTTPS enforcement: `trustProxies()` was never
 * configured at all. Without it, Laravel ignores every `X-Forwarded-*`
 * header regardless of who sent it, which silently breaks two things
 * behind any real reverse proxy (the exact deployment topology
 * docs/DEPLOYMENT.md already describes): the HSTS header (gated on
 * `$request->isSecure()`, which is always false without a trusted
 * `X-Forwarded-Proto`) and every per-IP rate limiter (`$request->ip()`
 * returns the proxy's own IP for every request, collapsing every real
 * client behind it onto one shared bucket). `phpunit.xml` sets
 * `TRUSTED_PROXIES=127.0.0.1` to match the test client's own
 * `REMOTE_ADDR`, so this test exercises the real header-handling path
 * rather than it being structurally impossible to reach in tests.
 */
class TrustedProxiesTest extends TestCase
{
    public function test_hsts_header_is_sent_when_a_trusted_proxy_reports_https(): void
    {
        $response = $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->getJson('/api/v1/health');

        $response->assertHeader('Strict-Transport-Security');
    }

    public function test_hsts_header_is_absent_on_a_plain_http_request(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_forwarded_client_ips_get_independent_rate_limit_buckets(): void
    {
        // Exhaust the 20/hour IP bucket for 1.1.1.1 — each request uses a
        // distinct phone number so the separate 5/hour phone bucket never
        // trips first and masks what's being tested here.
        for ($i = 0; $i < 20; $i++) {
            $this->withHeaders(['X-Forwarded-For' => '1.1.1.1'])
                ->postJson('/api/v1/auth/otp/send', [
                    'phone' => '+96650111'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'user_type' => 'customer',
                ])->assertOk();
        }

        $this->withHeaders(['X-Forwarded-For' => '1.1.1.1'])
            ->postJson('/api/v1/auth/otp/send', [
                'phone' => '+966501110020',
                'user_type' => 'customer',
            ])->assertStatus(429);

        // A different forwarded client IP must not share that exhausted
        // bucket — if X-Forwarded-For weren't honored, both requests
        // would resolve to the same REMOTE_ADDR and this would also 429.
        $this->withHeaders(['X-Forwarded-For' => '2.2.2.2'])
            ->postJson('/api/v1/auth/otp/send', [
                'phone' => '+966501110021',
                'user_type' => 'customer',
            ])->assertOk();
    }
}
