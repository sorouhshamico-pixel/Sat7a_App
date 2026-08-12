<?php

namespace App\Http\Controllers\Api\V1\Providers;

use App\Domain\Drivers\Actions\AddDriverAction;
use App\Http\Controllers\Concerns\ResolvesProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Providers\AddDriverRequest;
use App\Http\Requests\Api\V1\Providers\UpdateDriverAvailabilityRequest;
use App\Http\Resources\Api\V1\DriverResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderDriverController extends Controller
{
    use ResolvesProvider;

    public function index(Request $request): JsonResponse
    {
        $provider = $this->resolveProvider($request);
        $drivers = $provider->drivers()->with(['user', 'towTruck'])->get();

        return ApiResponse::success(['drivers' => DriverResource::collection($drivers)]);
    }

    public function store(AddDriverRequest $request, AddDriverAction $action): JsonResponse
    {
        $provider = $this->resolveProvider($request);

        $driver = $action->handle($provider, $request->validated(), $request->ip());
        $driver->load('user');

        return ApiResponse::success([
            'driver' => new DriverResource($driver),
            'message' => 'A verification code has been sent to the driver to complete registration.',
        ], status: 201);
    }

    public function updateAvailability(UpdateDriverAvailabilityRequest $request, string $driverPublicId): JsonResponse
    {
        $provider = $this->resolveProvider($request);
        $driver = $provider->drivers()->where('public_id', $driverPublicId)->firstOrFail();

        $driver->update(['is_available' => $request->boolean('is_available')]);

        return ApiResponse::success(['driver' => new DriverResource($driver->load('user'))]);
    }
}
