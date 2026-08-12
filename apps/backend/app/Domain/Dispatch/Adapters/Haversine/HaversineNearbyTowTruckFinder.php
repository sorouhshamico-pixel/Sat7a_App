<?php

namespace App\Domain\Dispatch\Adapters\Haversine;

use App\Domain\Dispatch\Contracts\NearbyTowTruckFinder;
use App\Domain\Dispatch\DataTransferObjects\DispatchCandidate;
use App\Domain\Drivers\Enums\DriverStatus;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Fleet\Enums\TowTruckStatus;
use App\Domain\Fleet\Models\TowTruck;
use App\Domain\Maps\DataTransferObjects\Coordinates;
use App\Domain\Providers\Enums\ProviderStatus;

/**
 * The only implementation of NearbyTowTruckFinder today. It is a
 * deliberate, temporary stand-in for a real PostGIS `ST_DWithin` radius
 * query — this project's own conventions (docs/DATABASE_SCHEMA.md
 * §Geography) say nearby queries should use PostGIS, never manual
 * Haversine math in PHP.
 *
 * Two things are missing to do this properly, not just the extension:
 * PostGIS itself still isn't installed on the dev machine (see
 * docs/DEPLOYMENT.md §One-time PostGIS setup), *and* `tow_trucks` has no
 * `location geography(Point,4326)` column yet — that migration was
 * deferred in Phase 6 for the same reason. Writing a PostGIS-backed
 * implementation now would be unverifiable code shipped on faith, which
 * this project avoids (see docs/ROADMAP.md Phase 6). This adapter fetches
 * the small set of currently-available trucks and filters/sorts in PHP
 * instead — acceptable at current scale, not the long-term design. Once
 * PostGIS is active, add the geography column and a
 * `PostGisNearbyTowTruckFinder`, and swap the binding in
 * `App\Providers\DispatchServiceProvider` — nothing calling this
 * interface needs to change.
 */
class HaversineNearbyTowTruckFinder implements NearbyTowTruckFinder
{
    private const EARTH_RADIUS_METERS = 6_371_000;

    /**
     * @param  list<int>  $excludeTowTruckIds
     * @return list<DispatchCandidate>
     */
    public function find(
        Coordinates $origin,
        ServiceCapability $serviceType,
        int $radiusMeters,
        int $limit,
        array $excludeTowTruckIds = [],
    ): array {
        $query = TowTruck::query()
            ->where('status', TowTruckStatus::Available)
            ->whereNotNull('driver_id')
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->with(['driver', 'provider']);

        if ($excludeTowTruckIds !== []) {
            $query->whereNotIn('id', $excludeTowTruckIds);
        }

        return $query->get()
            ->filter(fn (TowTruck $truck): bool => in_array($serviceType->value, $truck->service_capabilities, true))
            ->filter(fn (TowTruck $truck): bool => $truck->provider->status === ProviderStatus::Approved)
            ->filter(fn (TowTruck $truck): bool => $truck->driver->status === DriverStatus::Active && $truck->driver->is_available)
            ->map(function (TowTruck $truck) use ($origin): DispatchCandidate {
                $distance = $this->haversineDistance($origin, new Coordinates(
                    (float) $truck->current_latitude,
                    (float) $truck->current_longitude,
                ));

                return new DispatchCandidate($truck, (int) round($distance));
            })
            ->filter(fn (DispatchCandidate $candidate): bool => $candidate->distanceMeters <= $radiusMeters)
            ->sortBy(fn (DispatchCandidate $candidate): int => $candidate->distanceMeters)
            ->take($limit)
            ->values()
            ->all();
    }

    private function haversineDistance(Coordinates $a, Coordinates $b): float
    {
        $latDelta = deg2rad($b->latitude - $a->latitude);
        $lngDelta = deg2rad($b->longitude - $a->longitude);

        $h = sin($latDelta / 2) ** 2
            + cos(deg2rad($a->latitude)) * cos(deg2rad($b->latitude)) * sin($lngDelta / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1, sqrt($h)));
    }
}
