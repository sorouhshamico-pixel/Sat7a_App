<?php

namespace App\Domain\Maps\Adapters\Fake;

use App\Domain\Maps\Contracts\RoutingProvider;
use App\Domain\Maps\DataTransferObjects\Coordinates;
use App\Domain\Maps\DataTransferObjects\RouteInfo;

/**
 * Straight-line (haversine) distance with a flat assumed average speed —
 * good enough to exercise pricing/dispatch logic in dev/tests without a
 * real routing API. Never used for the real "nearby providers" query,
 * which is a PostGIS radius search, not this (see docs/ARCHITECTURE.md §6).
 */
class FakeRoutingProvider implements RoutingProvider
{
    private const EARTH_RADIUS_METERS = 6_371_000;

    private const ASSUMED_AVERAGE_SPEED_METERS_PER_SECOND = 11.1; // ~40 km/h city driving

    public function route(Coordinates $origin, Coordinates $destination): RouteInfo
    {
        $distanceMeters = (int) round($this->haversineDistance($origin, $destination));
        $durationSeconds = (int) round($distanceMeters / self::ASSUMED_AVERAGE_SPEED_METERS_PER_SECOND);

        return new RouteInfo($distanceMeters, $durationSeconds);
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
