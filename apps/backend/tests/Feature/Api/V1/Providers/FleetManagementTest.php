<?php

namespace Tests\Feature\Api\V1\Providers;

use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Providers\Models\Provider;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function registerProvider(string $phone = '+966501112233'): Provider
    {
        $this->postJson('/api/v1/providers/register', [
            'business_name' => 'شركة النقل السريع',
            'owner_name' => 'محمد أحمد',
            'contact_phone' => $phone,
        ])->assertCreated();

        return Provider::query()->where('contact_phone', $phone)->firstOrFail();
    }

    private function addDriver(Provider $provider, string $token, string $phone = '+966502223344'): string
    {
        $response = $this->actingAsToken('POST', $token, '/api/v1/providers/me/drivers', [
            'name' => 'سالم القحطاني',
            'phone' => $phone,
        ]);

        return $response->json('data.driver.id');
    }

    public function test_owner_can_add_a_tow_truck(): void
    {
        $provider = $this->registerProvider();
        $token = $this->tokenFor($provider->owner);

        $response = $this->withToken($token)->postJson('/api/v1/providers/me/fleet', [
            'manufacturer' => 'Isuzu',
            'model' => 'NPR',
            'year' => 2022,
            'plate_number' => 'ABC-1234',
            'service_capabilities' => [ServiceCapability::StandardFlatbed->value],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.tow_truck.status', 'offline');
        $this->assertDatabaseHas('tow_trucks', ['plate_number' => 'ABC-1234', 'provider_id' => $provider->id]);
    }

    public function test_plate_number_must_be_unique(): void
    {
        $provider = $this->registerProvider();
        $token = $this->tokenFor($provider->owner);

        $this->withToken($token)->postJson('/api/v1/providers/me/fleet', [
            'manufacturer' => 'Isuzu',
            'model' => 'NPR',
            'year' => 2022,
            'plate_number' => 'ABC-1234',
            'service_capabilities' => [ServiceCapability::StandardFlatbed->value],
        ])->assertCreated();

        $response = $this->withToken($token)->postJson('/api/v1/providers/me/fleet', [
            'manufacturer' => 'Hino',
            'model' => '300',
            'year' => 2021,
            'plate_number' => 'ABC-1234',
            'service_capabilities' => [ServiceCapability::HeavyVehicle->value],
        ]);

        $response->assertStatus(422);
    }

    public function test_owner_can_assign_a_driver_to_a_tow_truck(): void
    {
        $provider = $this->registerProvider();
        $token = $this->tokenFor($provider->owner);

        $truckResponse = $this->withToken($token)->postJson('/api/v1/providers/me/fleet', [
            'manufacturer' => 'Isuzu',
            'model' => 'NPR',
            'year' => 2022,
            'plate_number' => 'ABC-1234',
            'service_capabilities' => [ServiceCapability::StandardFlatbed->value],
        ]);
        $truckId = $truckResponse->json('data.tow_truck.id');

        $driverId = $this->addDriver($provider, $token);

        $response = $this->withToken($token)->patchJson("/api/v1/providers/me/fleet/{$truckId}/driver", [
            'driver_id' => $driverId,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.tow_truck.driver.id', $driverId);
    }

    public function test_a_driver_already_assigned_to_another_truck_cannot_be_assigned_again(): void
    {
        $provider = $this->registerProvider();
        $token = $this->tokenFor($provider->owner);

        $truckA = $this->withToken($token)->postJson('/api/v1/providers/me/fleet', [
            'manufacturer' => 'Isuzu', 'model' => 'NPR', 'year' => 2022,
            'plate_number' => 'ABC-1234', 'service_capabilities' => [ServiceCapability::StandardFlatbed->value],
        ])->json('data.tow_truck.id');

        $truckB = $this->withToken($token)->postJson('/api/v1/providers/me/fleet', [
            'manufacturer' => 'Hino', 'model' => '300', 'year' => 2021,
            'plate_number' => 'XYZ-5678', 'service_capabilities' => [ServiceCapability::HeavyVehicle->value],
        ])->json('data.tow_truck.id');

        $driverId = $this->addDriver($provider, $token);

        $this->withToken($token)->patchJson("/api/v1/providers/me/fleet/{$truckA}/driver", ['driver_id' => $driverId])
            ->assertOk();

        $response = $this->withToken($token)->patchJson("/api/v1/providers/me/fleet/{$truckB}/driver", ['driver_id' => $driverId]);

        $response->assertStatus(422);
    }

    public function test_a_driver_from_another_provider_cannot_be_assigned(): void
    {
        $providerA = $this->registerProvider('+966501112233');
        $tokenA = $this->tokenFor($providerA->owner);

        $truckId = $this->withToken($tokenA)->postJson('/api/v1/providers/me/fleet', [
            'manufacturer' => 'Isuzu', 'model' => 'NPR', 'year' => 2022,
            'plate_number' => 'ABC-1234', 'service_capabilities' => [ServiceCapability::StandardFlatbed->value],
        ])->json('data.tow_truck.id');

        $providerB = $this->registerProvider('+966501112244');
        $tokenB = $this->tokenFor($providerB->owner);
        $foreignDriverId = $this->addDriver($providerB, $tokenB, '+966502223355');

        $response = $this->actingAsToken('PATCH', $tokenA, "/api/v1/providers/me/fleet/{$truckId}/driver", [
            'driver_id' => $foreignDriverId,
        ]);

        $response->assertStatus(404);
    }

    public function test_valid_status_transition_succeeds(): void
    {
        $provider = $this->registerProvider();
        $token = $this->tokenFor($provider->owner);

        $truckId = $this->withToken($token)->postJson('/api/v1/providers/me/fleet', [
            'manufacturer' => 'Isuzu', 'model' => 'NPR', 'year' => 2022,
            'plate_number' => 'ABC-1234', 'service_capabilities' => [ServiceCapability::StandardFlatbed->value],
        ])->json('data.tow_truck.id');

        $response = $this->withToken($token)->patchJson("/api/v1/providers/me/fleet/{$truckId}/status", [
            'status' => 'available',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.tow_truck.status', 'available');
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $provider = $this->registerProvider();
        $token = $this->tokenFor($provider->owner);

        $truckId = $this->withToken($token)->postJson('/api/v1/providers/me/fleet', [
            'manufacturer' => 'Isuzu', 'model' => 'NPR', 'year' => 2022,
            'plate_number' => 'ABC-1234', 'service_capabilities' => [ServiceCapability::StandardFlatbed->value],
        ])->json('data.tow_truck.id');

        // offline -> on_trip is not a valid direct transition, and on_trip
        // isn't provider-settable at all.
        $response = $this->withToken($token)->patchJson("/api/v1/providers/me/fleet/{$truckId}/status", [
            'status' => 'on_trip',
        ]);

        $response->assertStatus(422);
    }

    public function test_fleet_summary_reports_correct_counts(): void
    {
        $provider = $this->registerProvider();
        $token = $this->tokenFor($provider->owner);

        $this->withToken($token)->postJson('/api/v1/providers/me/fleet', [
            'manufacturer' => 'Isuzu', 'model' => 'NPR', 'year' => 2022,
            'plate_number' => 'ABC-1234', 'service_capabilities' => [ServiceCapability::StandardFlatbed->value],
        ])->assertCreated();

        $this->addDriver($provider, $token);

        $response = $this->withToken($token)->getJson('/api/v1/providers/me/fleet/summary');

        $response->assertOk();
        $response->assertJsonPath('data.summary.total_tow_trucks', 1);
        $response->assertJsonPath('data.summary.total_drivers', 1);
    }
}
