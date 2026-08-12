<?php

namespace App\Domain\Dispatch\Contracts;

use App\Domain\Dispatch\DataTransferObjects\DispatchCandidate;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Maps\DataTransferObjects\Coordinates;

/**
 * Business/domain code never queries `tow_trucks` for a radius match
 * directly — see docs/DISPATCH_ENGINE.md §Candidate filtering. The only
 * implementation today (`App\Domain\Dispatch\Adapters\Haversine\HaversineNearbyTowTruckFinder`)
 * is a deliberate, documented stand-in for a PostGIS `ST_DWithin` query —
 * see that class's docblock for why.
 */
interface NearbyTowTruckFinder
{
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
    ): array;
}
