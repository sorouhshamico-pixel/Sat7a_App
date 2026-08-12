<?php

namespace App\Domain\Maps\Adapters\Fake;

use App\Domain\Maps\Contracts\PlacesProvider;
use App\Domain\Maps\DataTransferObjects\Coordinates;
use App\Domain\Maps\DataTransferObjects\PlaceDetails;
use App\Domain\Maps\DataTransferObjects\PlaceSuggestion;

class FakePlacesProvider implements PlacesProvider
{
    public function autocomplete(string $query, ?string $sessionToken = null): array
    {
        if (trim($query) === '') {
            return [];
        }

        return [
            new PlaceSuggestion(
                placeId: 'fake-place-'.substr(md5($query.'-1'), 0, 12),
                description: "{$query}, حي النرجس، الرياض",
            ),
            new PlaceSuggestion(
                placeId: 'fake-place-'.substr(md5($query.'-2'), 0, 12),
                description: "{$query}, حي الملقا، الرياض",
            ),
        ];
    }

    public function details(string $placeId, ?string $sessionToken = null): PlaceDetails
    {
        $hash = crc32($placeId);

        return new PlaceDetails(
            placeId: $placeId,
            formattedAddress: 'الرياض، المملكة العربية السعودية',
            coordinates: new Coordinates(
                24.7136 + ((($hash % 1000) / 1000) - 0.5) * 0.1,
                46.6753 + (((intdiv($hash, 1000) % 1000) / 1000) - 0.5) * 0.1,
            ),
        );
    }
}
