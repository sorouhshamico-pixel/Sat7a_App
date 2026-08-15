<?php

namespace App\Http\Controllers\Api\V1\Drivers;

use App\Domain\Tracking\Actions\RecordLocationPingAction;
use App\Http\Controllers\Concerns\ResolvesDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Drivers\RecordLocationPingRequest;
use App\Http\Resources\Api\V1\OrderLocationPingResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class LocationController extends Controller
{
    use ResolvesDriver;

    public function store(RecordLocationPingRequest $request, RecordLocationPingAction $action): JsonResponse
    {
        $driver = $this->resolveDriver($request);

        $recordedAt = $request->filled('recorded_at') ? Carbon::parse($request->string('recorded_at')->toString()) : null;

        $ping = $action->handle(
            driver: $driver,
            latitude: $request->float('latitude'),
            longitude: $request->float('longitude'),
            heading: $request->filled('heading') ? $request->integer('heading') : null,
            speedKmh: $request->filled('speed_kmh') ? $request->integer('speed_kmh') : null,
            recordedAt: $recordedAt,
        );

        return ApiResponse::success([
            'tracked' => $ping !== null,
            'ping' => $ping === null ? null : new OrderLocationPingResource($ping),
        ]);
    }
}
