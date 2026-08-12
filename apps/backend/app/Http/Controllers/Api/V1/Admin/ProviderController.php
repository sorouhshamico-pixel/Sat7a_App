<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Providers\Actions\ApproveProviderAction;
use App\Domain\Providers\Actions\RejectProviderAction;
use App\Domain\Providers\Actions\SuspendProviderAction;
use App\Domain\Providers\Models\Provider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReasonRequest;
use App\Http\Resources\Api\V1\DocumentResource;
use App\Http\Resources\Api\V1\ProviderResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Provider::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $providers = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            data: ['providers' => ProviderResource::collection($providers->items())],
            meta: [
                'current_page' => $providers->currentPage(),
                'per_page' => $providers->perPage(),
                'total' => $providers->total(),
            ],
        );
    }

    public function show(Provider $provider): JsonResponse
    {
        return ApiResponse::success([
            'provider' => new ProviderResource($provider),
            'documents' => DocumentResource::collection($provider->documents()->get()),
        ]);
    }

    public function approve(Request $request, Provider $provider, ApproveProviderAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return ApiResponse::success(['provider' => new ProviderResource($action->handle($provider, $actor))]);
    }

    public function reject(ReasonRequest $request, Provider $provider, RejectProviderAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $provider = $action->handle($provider, $actor, $request->string('reason')->toString());

        return ApiResponse::success(['provider' => new ProviderResource($provider)]);
    }

    public function suspend(ReasonRequest $request, Provider $provider, SuspendProviderAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $provider = $action->handle($provider, $actor, $request->string('reason')->toString());

        return ApiResponse::success(['provider' => new ProviderResource($provider)]);
    }
}
