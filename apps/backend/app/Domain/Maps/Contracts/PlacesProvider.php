<?php

namespace App\Domain\Maps\Contracts;

use App\Domain\Maps\DataTransferObjects\PlaceDetails;
use App\Domain\Maps\DataTransferObjects\PlaceSuggestion;
use App\Domain\Maps\Exceptions\MapsProviderException;

interface PlacesProvider
{
    /**
     * @return list<PlaceSuggestion>
     *
     * @throws MapsProviderException
     */
    public function autocomplete(string $query, ?string $sessionToken = null): array;

    /**
     * @throws MapsProviderException
     */
    public function details(string $placeId, ?string $sessionToken = null): PlaceDetails;
}
