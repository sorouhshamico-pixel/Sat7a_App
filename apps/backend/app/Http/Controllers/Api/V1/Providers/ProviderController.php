<?php

namespace App\Http\Controllers\Api\V1\Providers;

use App\Domain\Providers\Actions\RegisterProviderAction;
use App\Domain\Providers\Models\Provider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Providers\RegisterProviderRequest;
use App\Http\Requests\Api\V1\Providers\UpdateProviderRequest;
use App\Http\Resources\Api\V1\ProviderResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function register(RegisterProviderRequest $request, RegisterProviderAction $action): JsonResponse
    {
        $provider = $action->handle($request->validated(), $request->ip());

        return ApiResponse::success([
            'provider' => new ProviderResource($provider),
            'message' => 'A verification code has been sent to complete registration.',
        ], status: 201);
    }

    /**
     * The provider owned by the authenticated provider_staff user. Fleet
     * manager/driver membership resolution (a provider_staff user who
     * isn't the owner) lands with the Fleet & Drivers domain in Phase 4.
     */
    public function show(Request $request): JsonResponse
    {
        $provider = $this->ownedProvider($request);

        return ApiResponse::success(['provider' => new ProviderResource($provider)]);
    }

    public function update(UpdateProviderRequest $request): JsonResponse
    {
        $provider = $this->ownedProvider($request);
        $provider->update($request->validated());

        return ApiResponse::success(['provider' => new ProviderResource($provider)]);
    }

    private function ownedProvider(Request $request): Provider
    {
        return Provider::query()->where('owner_id', $request->user()->id)->firstOrFail();
    }
}
