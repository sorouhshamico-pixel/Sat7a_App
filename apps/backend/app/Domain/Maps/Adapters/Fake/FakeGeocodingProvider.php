<?php

namespace App\Domain\Maps\Adapters\Fake;

use App\Domain\Maps\Contracts\GeocodingProvider;
use App\Domain\Maps\DataTransferObjects\Coordinates;
use App\Domain\Maps\DataTransferObjects\GeocodedAddress;

/**
 * Deterministic, no external calls — used whenever GOOGLE_MAPS_API_KEY is
 * blank (local dev, CI, tests). Every address maps to a stable point near
 * Riyadh so the same input always produces the same output, without
 * inventing real geocoding (see docs/SECURITY.md §Secrets).
 */
class FakeGeocodingProvider implements GeocodingProvider
{
    private const RIYADH_LATITUDE = 24.7136;

    private const RIYADH_LONGITUDE = 46.6753;

    public function geocode(string $address): GeocodedAddress
    {
        [$latOffset, $lngOffset] = $this->deterministicOffset($address);

        return new GeocodedAddress(
            coordinates: new Coordinates(
                self::RIYADH_LATITUDE + $latOffset,
                self::RIYADH_LONGITUDE + $lngOffset,
            ),
            formattedAddress: $address,
            placeId: 'fake-place-'.substr(md5($address), 0, 12),
        );
    }

    public function reverseGeocode(Coordinates $coordinates): GeocodedAddress
    {
        return new GeocodedAddress(
            coordinates: $coordinates,
            formattedAddress: sprintf('Riyadh, Saudi Arabia (%.4f, %.4f)', $coordinates->latitude, $coordinates->longitude),
        );
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function deterministicOffset(string $seed): array
    {
        $hash = crc32($seed);

        // +/- ~0.05 degrees (roughly 5km) around central Riyadh.
        $latOffset = ((($hash % 1000) / 1000) - 0.5) * 0.1;
        $lngOffset = (((intdiv($hash, 1000) % 1000) / 1000) - 0.5) * 0.1;

        return [$latOffset, $lngOffset];
    }
}
