<?php

namespace App\Http\Controllers\Api\V1\Customers;

use App\Domain\Customers\Actions\StorePublicImageAction;
use App\Http\Controllers\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customers\AddVehicleRequest;
use App\Http\Requests\Api\V1\Customers\UpdateVehicleRequest;
use App\Http\Resources\Api\V1\VehicleResource;
use App\Http\Responses\ApiResponse;
use App\Support\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    use ResolvesCustomer;

    public function index(Request $request): JsonResponse
    {
        $vehicles = $this->resolveCustomer($request)->vehicles;

        return ApiResponse::success(['vehicles' => VehicleResource::collection($vehicles)]);
    }

    public function store(AddVehicleRequest $request, StorePublicImageAction $imageAction): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $vehicle = $customer->vehicles()->make($request->validated());

        if ($request->hasFile('image')) {
            $vehicle->image_path = $imageAction->handle($request->file('image'), 'vehicles');
        }

        $vehicle->save();

        return ApiResponse::success(['vehicle' => new VehicleResource($vehicle)], status: 201);
    }

    public function update(UpdateVehicleRequest $request, string $vehiclePublicId): JsonResponse
    {
        $vehicle = $this->resolveCustomer($request)->vehicles()->where('public_id', $vehiclePublicId)->firstOrFail();
        $vehicle->update($request->validated());

        return ApiResponse::success(['vehicle' => new VehicleResource($vehicle)]);
    }

    public function destroy(Request $request, string $vehiclePublicId): JsonResponse
    {
        $vehicle = $this->resolveCustomer($request)->vehicles()->where('public_id', $vehiclePublicId)->first();

        if ($vehicle === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Vehicle not found.', 404);
        }

        $vehicle->delete();

        return ApiResponse::success(['message' => 'Vehicle removed.']);
    }
}
