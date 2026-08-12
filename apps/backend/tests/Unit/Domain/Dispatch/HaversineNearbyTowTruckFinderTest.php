<?php

namespace Tests\Unit\Domain\Dispatch;

use App\Domain\Dispatch\Adapters\Haversine\HaversineNearbyTowTruckFinder;
use App\Domain\Drivers\Enums\DriverStatus;
use App\Domain\Drivers\Models\Driver;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Fleet\Enums\TowTruckStatus;
use App\Domain\Fleet\Models\TowTruck;
use App\Domain\Maps\DataTransferObjects\Coordinates;
use App\Domain\Providers\Enums\ProviderStatus;
use App\Domain\Providers\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HaversineNearbyTowTruckFinderTest extends TestCase
{
    use RefreshDatabase;

    private const RIYADH_CENTER_LAT = 24.7136;

    private const RIYADH_CENTER_LNG = 46.6753;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeTruck(array $overrides = []): TowTruck
    {
        static $n = 0;
        $n++;

        $provider = new Provider([
            'business_name' => "Provider {$n}",
            'contact_phone' => "+96650000{$n}00",
        ]);
        $provider->owner_id = User::factory()->create()->id;
        $provider->status = $overrides['provider_status'] ?? ProviderStatus::Approved;
        $provider->save();

        $driverUser = User::factory()->providerStaff()->create();
        $driver = new Driver([]);
        $driver->provider_id = $provider->id;
        $driver->user_id = $driverUser->id;
        $driver->status = $overrides['driver_status'] ?? DriverStatus::Active;
        $driver->is_available = $overrides['driver_is_available'] ?? true;
        $driver->save();

        $truck = new TowTruck([
            'manufacturer' => 'Isuzu',
            'model' => 'NPR',
            'year' => 2022,
            'plate_number' => 'PLT-'.$n,
            'service_capabilities' => $overrides['service_capabilities'] ?? [ServiceCapability::StandardFlatbed->value],
            'status' => $overrides['status'] ?? TowTruckStatus::Available,
            'current_latitude' => $overrides['current_latitude'] ?? self::RIYADH_CENTER_LAT,
            'current_longitude' => $overrides['current_longitude'] ?? self::RIYADH_CENTER_LNG,
        ]);
        $noDriver = $overrides['no_driver'] ?? false;
        $truck->provider_id = $provider->id;
        $truck->driver_id = $noDriver ? null : $driver->id;
        $truck->save();

        return $truck;
    }

    public function test_finds_a_truck_at_the_exact_origin(): void
    {
        $truck = $this->makeTruck();

        $finder = new HaversineNearbyTowTruckFinder;
        $candidates = $finder->find(
            origin: new Coordinates(self::RIYADH_CENTER_LAT, self::RIYADH_CENTER_LNG),
            serviceType: ServiceCapability::StandardFlatbed,
            radiusMeters: 5000,
            limit: 5,
        );

        $this->assertCount(1, $candidates);
        $this->assertSame($truck->id, $candidates[0]->towTruck->id);
        $this->assertSame(0, $candidates[0]->distanceMeters);
    }

    public function test_excludes_a_truck_outside_the_radius(): void
    {
        // ~55km north — well outside a 5km radius.
        $this->makeTruck(['current_latitude' => 25.2, 'current_longitude' => 46.6753]);

        $finder = new HaversineNearbyTowTruckFinder;
        $candidates = $finder->find(
            origin: new Coordinates(self::RIYADH_CENTER_LAT, self::RIYADH_CENTER_LNG),
            serviceType: ServiceCapability::StandardFlatbed,
            radiusMeters: 5000,
            limit: 5,
        );

        $this->assertCount(0, $candidates);
    }

    public function test_excludes_a_truck_that_is_not_available(): void
    {
        $this->makeTruck(['status' => TowTruckStatus::Offline]);

        $finder = new HaversineNearbyTowTruckFinder;
        $candidates = $finder->find(
            origin: new Coordinates(self::RIYADH_CENTER_LAT, self::RIYADH_CENTER_LNG),
            serviceType: ServiceCapability::StandardFlatbed,
            radiusMeters: 5000,
            limit: 5,
        );

        $this->assertCount(0, $candidates);
    }

    public function test_excludes_a_truck_whose_driver_is_offline(): void
    {
        $this->makeTruck(['driver_is_available' => false]);

        $finder = new HaversineNearbyTowTruckFinder;
        $candidates = $finder->find(
            origin: new Coordinates(self::RIYADH_CENTER_LAT, self::RIYADH_CENTER_LNG),
            serviceType: ServiceCapability::StandardFlatbed,
            radiusMeters: 5000,
            limit: 5,
        );

        $this->assertCount(0, $candidates);
    }

    public function test_excludes_a_truck_from_a_provider_that_is_not_approved(): void
    {
        $this->makeTruck(['provider_status' => ProviderStatus::Suspended]);

        $finder = new HaversineNearbyTowTruckFinder;
        $candidates = $finder->find(
            origin: new Coordinates(self::RIYADH_CENTER_LAT, self::RIYADH_CENTER_LNG),
            serviceType: ServiceCapability::StandardFlatbed,
            radiusMeters: 5000,
            limit: 5,
        );

        $this->assertCount(0, $candidates);
    }

    public function test_excludes_a_truck_without_the_required_service_capability(): void
    {
        $this->makeTruck(['service_capabilities' => [ServiceCapability::HeavyVehicle->value]]);

        $finder = new HaversineNearbyTowTruckFinder;
        $candidates = $finder->find(
            origin: new Coordinates(self::RIYADH_CENTER_LAT, self::RIYADH_CENTER_LNG),
            serviceType: ServiceCapability::StandardFlatbed,
            radiusMeters: 5000,
            limit: 5,
        );

        $this->assertCount(0, $candidates);
    }

    public function test_sorts_results_by_distance_ascending(): void
    {
        $far = $this->makeTruck(['current_latitude' => 24.74, 'current_longitude' => 46.6753]);
        $near = $this->makeTruck(['current_latitude' => 24.715, 'current_longitude' => 46.6753]);

        $finder = new HaversineNearbyTowTruckFinder;
        $candidates = $finder->find(
            origin: new Coordinates(self::RIYADH_CENTER_LAT, self::RIYADH_CENTER_LNG),
            serviceType: ServiceCapability::StandardFlatbed,
            radiusMeters: 10000,
            limit: 5,
        );

        $this->assertCount(2, $candidates);
        $this->assertSame($near->id, $candidates[0]->towTruck->id);
        $this->assertSame($far->id, $candidates[1]->towTruck->id);
    }

    public function test_respects_the_exclude_list(): void
    {
        $truck = $this->makeTruck();

        $finder = new HaversineNearbyTowTruckFinder;
        $candidates = $finder->find(
            origin: new Coordinates(self::RIYADH_CENTER_LAT, self::RIYADH_CENTER_LNG),
            serviceType: ServiceCapability::StandardFlatbed,
            radiusMeters: 5000,
            limit: 5,
            excludeTowTruckIds: [$truck->id],
        );

        $this->assertCount(0, $candidates);
    }

    public function test_respects_the_limit(): void
    {
        $this->makeTruck();
        $this->makeTruck();
        $this->makeTruck();

        $finder = new HaversineNearbyTowTruckFinder;
        $candidates = $finder->find(
            origin: new Coordinates(self::RIYADH_CENTER_LAT, self::RIYADH_CENTER_LNG),
            serviceType: ServiceCapability::StandardFlatbed,
            radiusMeters: 5000,
            limit: 2,
        );

        $this->assertCount(2, $candidates);
    }
}
