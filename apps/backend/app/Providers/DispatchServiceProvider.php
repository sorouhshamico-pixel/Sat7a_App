<?php

namespace App\Providers;

use App\Domain\Dispatch\Adapters\Haversine\HaversineNearbyTowTruckFinder;
use App\Domain\Dispatch\Contracts\NearbyTowTruckFinder;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the nearby-tow-truck-search implementation. Only one exists today
 * (see App\Domain\Dispatch\Adapters\Haversine\HaversineNearbyTowTruckFinder
 * for why) — this provider is the single place a future PostGIS-backed
 * implementation gets swapped in, so nothing calling the
 * NearbyTowTruckFinder interface needs to change when that happens.
 */
class DispatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NearbyTowTruckFinder::class, HaversineNearbyTowTruckFinder::class);
    }
}
