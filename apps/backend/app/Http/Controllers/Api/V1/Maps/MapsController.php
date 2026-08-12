<?php

namespace App\Http\Controllers\Api\V1\Maps;

use App\Domain\Maps\Contracts\GeocodingProvider;
use App\Domain\Maps\Contracts\PlacesProvider;
use App\Domain\Maps\Contracts\RoutingProvider;
use App\Domain\Maps\DataTransferObjects\Coordinates;
use App\Domain\Maps\Exceptions\MapsProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Maps\AutocompleteRequest;
use App\Http\Requests\Api\V1\Maps\GeocodeRequest;
use App\Http\Requests\Api\V1\Maps\ReverseGeocodeRequest;
use App\Http\Requests\Api\V1\Maps\RouteRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Every call is server-side, so the API key never reaches the client (see
 * docs/ARCHITECTURE.md §6). Guests can use these before authenticating —
 * building a quote doesn't require login (see docs/PRODUCT_REQUIREMENTS.md
 * §Core customer journey) — so they're rate-limited instead of gated
 * behind auth.
 */
class MapsController extends Controller
{
    public function geocode(GeocodeRequest $request, GeocodingProvider $provider): JsonResponse
    {
        try {
            $result = $provider->geocode($request->string('address')->toString());
        } catch (MapsProviderException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success([
            'coordinates' => $result->coordinates->toArray(),
            'formatted_address' => $result->formattedAddress,
            'place_id' => $result->placeId,
        ]);
    }

    public function reverseGeocode(ReverseGeocodeRequest $request, GeocodingProvider $provider): JsonResponse
    {
        $coordinates = new Coordinates($request->float('latitude'), $request->float('longitude'));

        try {
            $result = $provider->reverseGeocode($coordinates);
        } catch (MapsProviderException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success([
            'coordinates' => $result->coordinates->toArray(),
            'formatted_address' => $result->formattedAddress,
            'place_id' => $result->placeId,
        ]);
    }

    public function autocomplete(AutocompleteRequest $request, PlacesProvider $provider): JsonResponse
    {
        try {
            $suggestions = $provider->autocomplete(
                $request->string('query')->toString(),
                $request->string('session_token')->toString() ?: null,
            );
        } catch (MapsProviderException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success([
            'suggestions' => array_map(
                fn ($s) => ['place_id' => $s->placeId, 'description' => $s->description],
                $suggestions,
            ),
        ]);
    }

    public function placeDetails(Request $request, string $placeId, PlacesProvider $provider): JsonResponse
    {
        try {
            $details = $provider->details($placeId, $request->string('session_token')->toString() ?: null);
        } catch (MapsProviderException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success([
            'place_id' => $details->placeId,
            'formatted_address' => $details->formattedAddress,
            'coordinates' => $details->coordinates->toArray(),
        ]);
    }

    public function route(RouteRequest $request, RoutingProvider $provider): JsonResponse
    {
        $origin = new Coordinates($request->float('origin.latitude'), $request->float('origin.longitude'));
        $destination = new Coordinates($request->float('destination.latitude'), $request->float('destination.longitude'));

        try {
            $route = $provider->route($origin, $destination);
        } catch (MapsProviderException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success([
            'distance_meters' => $route->distanceMeters,
            'duration_seconds' => $route->durationSeconds,
        ]);
    }
}
