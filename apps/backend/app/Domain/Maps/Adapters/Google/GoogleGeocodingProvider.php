<?php

namespace App\Domain\Maps\Adapters\Google;

use App\Domain\Maps\Contracts\GeocodingProvider;
use App\Domain\Maps\DataTransferObjects\Coordinates;
use App\Domain\Maps\DataTransferObjects\GeocodedAddress;
use App\Domain\Maps\Exceptions\MapsProviderException;
use Illuminate\Support\Facades\Http;

class GoogleGeocodingProvider implements GeocodingProvider
{
    public function __construct(private readonly string $apiKey) {}

    public function geocode(string $address): GeocodedAddress
    {
        $response = $this->request('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $address,
            'region' => 'sa',
            'language' => 'ar',
        ]);

        $result = $response['results'][0] ?? null;

        if ($result === null) {
            throw MapsProviderException::notFound('Address');
        }

        return new GeocodedAddress(
            coordinates: new Coordinates(
                (float) $result['geometry']['location']['lat'],
                (float) $result['geometry']['location']['lng'],
            ),
            formattedAddress: $result['formatted_address'],
            placeId: $result['place_id'] ?? null,
        );
    }

    public function reverseGeocode(Coordinates $coordinates): GeocodedAddress
    {
        $response = $this->request('https://maps.googleapis.com/maps/api/geocode/json', [
            'latlng' => "{$coordinates->latitude},{$coordinates->longitude}",
            'language' => 'ar',
        ]);

        $result = $response['results'][0] ?? null;

        if ($result === null) {
            throw MapsProviderException::notFound('Location');
        }

        return new GeocodedAddress(
            coordinates: $coordinates,
            formattedAddress: $result['formatted_address'],
            placeId: $result['place_id'] ?? null,
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
            throw MapsProviderException::unavailable('geocoding request failed');
        }

        $body = $response->json();

        if (! in_array($body['status'] ?? null, ['OK', 'ZERO_RESULTS'], true)) {
            throw MapsProviderException::unavailable($body['status'] ?? 'unknown error');
        }

        return $body;
    }
}
