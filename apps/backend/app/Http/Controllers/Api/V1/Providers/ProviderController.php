<?php

namespace App\Http\Controllers\Api\V1\Providers;

use App\Domain\Providers\Actions\RegisterProviderAction;
use App\Http\Controllers\Concerns\ResolvesProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Providers\RegisterProviderRequest;
use App\Http\Requests\Api\V1\Providers\UpdateProviderRequest;
use App\Http\Resources\Api\V1\ProviderResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    use ResolvesProvider;

    public function register(RegisterProviderRequest $request, RegisterProviderAction $action): JsonResponse
    {
        $provider = $action->handle($request->validated(), $request->ip());

        return ApiResponse::success([
            'provider' => new ProviderResource($provider),
            'message' => 'A verification code has been sent to complete registration.',
        ], status: 201);
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(['provider' => new ProviderResource($this->resolveProvider($request))]);
    }

    public function update(UpdateProviderRequest $request): JsonResponse
    {
        $provider = $this->resolveProvider($request);
        $provider->update($request->validated());

        return ApiResponse::success(['provider' => new ProviderResource($provider)]);
    }
}
