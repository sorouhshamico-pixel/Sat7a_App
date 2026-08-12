<?php

namespace App\Domain\Maps\Contracts;

use App\Domain\Maps\DataTransferObjects\Coordinates;
use App\Domain\Maps\DataTransferObjects\GeocodedAddress;
use App\Domain\Maps\Exceptions\MapsProviderException;

/**
 * Business/domain code never talks to a mapping vendor SDK directly — see
 * docs/ARCHITECTURE.md §6 (Maps abstraction).
 */
interface GeocodingProvider
{
    /**
     * @throws MapsProviderException
     */
    public function geocode(string $address): GeocodedAddress;

    /**
     * @throws MapsProviderException
     */
    public function reverseGeocode(Coordinates $coordinates): GeocodedAddress;
}
