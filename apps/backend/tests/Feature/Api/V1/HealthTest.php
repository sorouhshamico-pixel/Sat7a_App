<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_reports_dependency_status(): void
    {
        Http::fake([
            '*/up' => Http::response(['health' => 'OK'], 200),
        ]);

        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['status', 'checks' => ['database', 'redis', 'reverb']],
            'meta',
            'errors',
        ]);
        $response->assertJsonPath('data.status', 'ok');
        $response->assertJsonPath('data.checks.reverb', true);
    }

    public function test_health_endpoint_reports_degraded_when_reverb_is_unreachable(): void
    {
        Http::fake([
            '*/up' => Http::response(null, 500),
        ]);

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(503);
        $response->assertJsonPath('data.status', 'degraded');
        $response->assertJsonPath('data.checks.reverb', false);
        // Database and Redis are unaffected by Reverb being down.
        $response->assertJsonPath('data.checks.database', true);
        $response->assertJsonPath('data.checks.redis', true);
    }
}
