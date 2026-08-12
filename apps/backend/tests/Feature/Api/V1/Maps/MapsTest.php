<?php

namespace Tests\Feature\Api\V1\Maps;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_geocode_is_public_and_returns_coordinates(): void
    {
        $response = $this->postJson('/api/v1/maps/geocode', ['address' => 'King Fahd Road, Riyadh']);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['coordinates' => ['latitude', 'longitude'], 'formatted_address', 'place_id']]);
    }

    public function test_geocode_requires_an_address(): void
    {
        $this->postJson('/api/v1/maps/geocode', [])->assertStatus(422);
    }

    public function test_reverse_geocode_returns_a_formatted_address(): void
    {
        $response = $this->postJson('/api/v1/maps/reverse-geocode', [
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.formatted_address'));
    }

    public function test_places_autocomplete_returns_suggestions(): void
    {
        $response = $this->getJson('/api/v1/maps/places/autocomplete?query=King+Fahd');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.suggestions'));
    }

    public function test_place_details_returns_coordinates(): void
    {
        $response = $this->getJson('/api/v1/maps/places/fake-place-abc123');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['place_id', 'formatted_address', 'coordinates']]);
    }

    public function test_route_computes_distance_and_duration(): void
    {
        $response = $this->postJson('/api/v1/maps/route', [
            'origin' => ['latitude' => 24.7136, 'longitude' => 46.6753],
            'destination' => ['latitude' => 24.7500, 'longitude' => 46.7200],
        ]);

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('data.distance_meters'));
        $this->assertGreaterThan(0, $response->json('data.duration_seconds'));
    }

    public function test_maps_endpoints_are_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/v1/maps/geocode', ['address' => "Address {$i}"]);
        }

        $response = $this->postJson('/api/v1/maps/geocode', ['address' => 'One too many']);

        $response->assertStatus(429);
    }
}
