<?php

namespace App\Providers;

use App\Domain\Maps\Adapters\Fake\FakeGeocodingProvider;
use App\Domain\Maps\Adapters\Fake\FakePlacesProvider;
use App\Domain\Maps\Adapters\Fake\FakeRoutingProvider;
use App\Domain\Maps\Adapters\Google\GoogleGeocodingProvider;
use App\Domain\Maps\Adapters\Google\GooglePlacesProvider;
use App\Domain\Maps\Adapters\Google\GoogleRoutingProvider;
use App\Domain\Maps\Contracts\GeocodingProvider;
use App\Domain\Maps\Contracts\PlacesProvider;
use App\Domain\Maps\Contracts\RoutingProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the fake adapters whenever no Google Maps API key is configured
 * (local dev, CI, tests) so nothing invents real geocoding or requires a
 * real key to run — see docs/SECURITY.md §Secrets. Swapping to a live key
 * is a config change only, never an application-code change.
 */
class MapsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $apiKey = config('services.google_maps.api_key');

        if (is_string($apiKey) && $apiKey !== '') {
            $this->app->bind(GeocodingProvider::class, fn () => new GoogleGeocodingProvider($apiKey));
            $this->app->bind(PlacesProvider::class, fn () => new GooglePlacesProvider($apiKey));
            $this->app->bind(RoutingProvider::class, fn () => new GoogleRoutingProvider($apiKey));

            return;
        }

        $this->app->bind(GeocodingProvider::class, FakeGeocodingProvider::class);
        $this->app->bind(PlacesProvider::class, FakePlacesProvider::class);
        $this->app->bind(RoutingProvider::class, FakeRoutingProvider::class);
    }
}
