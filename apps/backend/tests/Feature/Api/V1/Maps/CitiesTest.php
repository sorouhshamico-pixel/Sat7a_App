<?php

namespace Tests\Feature\Api\V1\Maps;

use Database\Seeders\CitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_cities_are_listed(): void
    {
        $this->seed(CitySeeder::class);

        $response = $this->getJson('/api/v1/cities');

        $response->assertOk();
        $cities = $response->json('data.cities');
        $this->assertCount(1, $cities);
        $this->assertSame('riyadh', $cities[0]['slug']);
    }
}
