<?php

namespace App\Domain\Maps\Adapters\Google;

use App\Domain\Maps\Contracts\PlacesProvider;
use App\Domain\Maps\DataTransferObjects\Coordinates;
use App\Domain\Maps\DataTransferObjects\PlaceDetails;
use App\Domain\Maps\DataTransferObjects\PlaceSuggestion;
use App\Domain\Maps\Exceptions\MapsProviderException;
use Illuminate\Support\Facades\Http;

/**
 * Uses a client-supplied session token where provided, so autocomplete +
 * details billing is grouped into a single session (see
 * docs/ARCHITECTURE.md §6 "Session tokens للـPlaces").
 */
class GooglePlacesProvider implements PlacesProvider
{
    public function __construct(private readonly string $apiKey) {}

    public function autocomplete(string $query, ?string $sessionToken = null): array
    {
        $response = $this->request('https://maps.googleapis.com/maps/api/place/autocomplete/json', array_filter([
            'input' => $query,
            'components' => 'country:sa',
            'language' => 'ar',
            'sessiontoken' => $sessionToken,
        ]));

        return array_map(
            fn (array $prediction) => new PlaceSuggestion($prediction['place_id'], $prediction['description']),
            $response['predictions'] ?? [],
        );
    }

    public function details(string $placeId, ?string $sessionToken = null): PlaceDetails
    {
        $response = $this->request('https://maps.googleapis.com/maps/api/place/details/json', array_filter([
            'place_id' => $placeId,
            'fields' => 'place_id,formatted_address,geometry',
            'language' => 'ar',
            'sessiontoken' => $sessionToken,
        ]));

        $result = $response['result'] ?? null;

        if ($result === null) {
            throw MapsProviderException::notFound('Place');
        }

        return new PlaceDetails(
            placeId: $result['place_id'],
            formattedAddress: $result['formatted_address'],
            coordinates: new Coordinates(
                (float) $result['geometry']['location']['lat'],
                (float) $result['geometry']['location']['lng'],
            ),
        );
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function request(string $url, array $query): array
    {
        $response = Http::timeout(5)->get($url, [...$query, 'key' => $this->apiKey]);

        if ($response->failed()) {
            throw MapsProviderException::unavailable('places request failed');
        }

        $body = $response->json();

        if (! in_array($body['status'] ?? null, ['OK', 'ZERO_RESULTS'], true)) {
            throw MapsProviderException::unavailable($body['status'] ?? 'unknown error');
        }

        return $body;
    }
}
