<?php

namespace App\Http\Controllers\Api\V1\Customers;

use App\Domain\Customers\Actions\AddSavedLocationAction;
use App\Domain\Customers\Exceptions\CustomerException;
use App\Http\Controllers\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customers\AddSavedLocationRequest;
use App\Http\Requests\Api\V1\Customers\UpdateSavedLocationRequest;
use App\Http\Resources\Api\V1\SavedLocationResource;
use App\Http\Responses\ApiResponse;
use App\Support\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedLocationController extends Controller
{
    use ResolvesCustomer;

    public function index(Request $request): JsonResponse
    {
        $locations = $this->resolveCustomer($request)->savedLocations;

        return ApiResponse::success(['locations' => SavedLocationResource::collection($locations)]);
    }

    public function store(AddSavedLocationRequest $request, AddSavedLocationAction $action): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        try {
            $location = $action->handle($customer, $request->validated());
        } catch (CustomerException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['location' => new SavedLocationResource($location)], status: 201);
    }

    public function update(UpdateSavedLocationRequest $request, string $locationPublicId): JsonResponse
    {
        $location = $this->resolveCustomer($request)->savedLocations()->where('public_id', $locationPublicId)->firstOrFail();
        $location->update($request->validated());

        return ApiResponse::success(['location' => new SavedLocationResource($location)]);
    }

    public function destroy(Request $request, string $locationPublicId): JsonResponse
    {
        $location = $this->resolveCustomer($request)->savedLocations()->where('public_id', $locationPublicId)->first();

        if ($location === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Saved location not found.', 404);
        }

        $location->delete();

        return ApiResponse::success(['message' => 'Saved location removed.']);
    }
}
