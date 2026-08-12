<?php

namespace App\Domain\Maps\Adapters\Google;

use App\Domain\Maps\Contracts\RoutingProvider;
use App\Domain\Maps\DataTransferObjects\Coordinates;
use App\Domain\Maps\DataTransferObjects\RouteInfo;
use App\Domain\Maps\Exceptions\MapsProviderException;
use Illuminate\Support\Facades\Http;

class GoogleRoutingProvider implements RoutingProvider
{
    public function __construct(private readonly string $apiKey) {}

    public function route(Coordinates $origin, Coordinates $destination): RouteInfo
    {
        $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'origins' => "{$origin->latitude},{$origin->longitude}",
            'destinations' => "{$destination->latitude},{$destination->longitude}",
            'language' => 'ar',
            'key' => $this->apiKey,
        ]);

        if ($response->failed()) {
            throw MapsProviderException::unavailable('routing request failed');
        }

        $body = $response->json();

        if (($body['status'] ?? null) !== 'OK') {
            throw MapsProviderException::unavailable($body['status'] ?? 'unknown error');
        }

        $element = $body['rows'][0]['elements'][0] ?? null;

        if ($element === null || $element['status'] !== 'OK') {
            throw MapsProviderException::notFound('Route');
        }

        return new RouteInfo(
            distanceMeters: (int) $element['distance']['value'],
            durationSeconds: (int) $element['duration']['value'],
        );
    }
}
