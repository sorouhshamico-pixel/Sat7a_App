<?php

namespace App\Http\Controllers\Api\V1\Providers;

use App\Domain\Fleet\Actions\AddTowTruckAction;
use App\Domain\Fleet\Actions\AssignDriverToTowTruckAction;
use App\Domain\Fleet\Actions\UpdateTowTruckStatusAction;
use App\Domain\Fleet\Enums\TowTruckStatus;
use App\Domain\Fleet\Exceptions\FleetException;
use App\Http\Controllers\Concerns\ResolvesProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Providers\AddTowTruckRequest;
use App\Http\Requests\Api\V1\Providers\AssignDriverRequest;
use App\Http\Requests\Api\V1\Providers\UpdateTowTruckStatusRequest;
use App\Http\Resources\Api\V1\TowTruckResource;
use App\Http\Responses\ApiResponse;
use App\Support\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderFleetController extends Controller
{
    use ResolvesProvider;

    public function index(Request $request): JsonResponse
    {
        $provider = $this->resolveProvider($request);
        $towTrucks = $provider->towTrucks()->with(['driver.user'])->get();

        return ApiResponse::success(['tow_trucks' => TowTruckResource::collection($towTrucks)]);
    }

    public function store(AddTowTruckRequest $request, AddTowTruckAction $action): JsonResponse
    {
        $provider = $this->resolveProvider($request);
        $towTruck = $action->handle($provider, $request->validated());

        return ApiResponse::success(['tow_truck' => new TowTruckResource($towTruck)], status: 201);
    }

    public function assignDriver(AssignDriverRequest $request, string $towTruckPublicId, AssignDriverToTowTruckAction $action): JsonResponse
    {
        $provider = $this->resolveProvider($request);
        $towTruck = $provider->towTrucks()->where('public_id', $towTruckPublicId)->firstOrFail();

        $driverId = null;
        if ($request->filled('driver_id')) {
            $driver = $provider->drivers()->where('public_id', $request->string('driver_id')->toString())->first();

            if ($driver === null) {
                return ApiResponse::error(ErrorCode::NotFound, 'Driver not found for this provider.', 404);
            }

            $driverId = $driver->id;
        }

        try {
            $towTruck = $action->handle($towTruck, $driverId);
        } catch (FleetException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['tow_truck' => new TowTruckResource($towTruck->load('driver.user'))]);
    }

    public function updateStatus(UpdateTowTruckStatusRequest $request, string $towTruckPublicId, UpdateTowTruckStatusAction $action): JsonResponse
    {
        $provider = $this->resolveProvider($request);
        $towTruck = $provider->towTrucks()->where('public_id', $towTruckPublicId)->firstOrFail();

        try {
            $towTruck = $action->handle($towTruck, TowTruckStatus::from($request->string('status')->toString()));
        } catch (FleetException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['tow_truck' => new TowTruckResource($towTruck)]);
    }

    public function summary(Request $request): JsonResponse
    {
        $provider = $this->resolveProvider($request);

        $statusCounts = $provider->towTrucks()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return ApiResponse::success([
            'summary' => [
                'total_tow_trucks' => $provider->towTrucks()->count(),
                'total_drivers' => $provider->drivers()->count(),
                'available_drivers' => $provider->drivers()->where('is_available', true)->count(),
                'tow_trucks_by_status' => $statusCounts,
            ],
        ]);
    }
}
