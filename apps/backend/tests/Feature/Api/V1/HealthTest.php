<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_reports_dependency_status(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['status', 'checks' => ['database', 'redis']],
            'meta',
            'errors',
        ]);
        $response->assertJsonPath('data.status', 'ok');
    }
}
