<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Pricing\Actions\ActivatePricingRuleVersionAction;
use App\Domain\Pricing\Actions\CreatePricingRuleVersionAction;
use App\Domain\Pricing\Models\PricingRuleVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CreatePricingRuleVersionRequest;
use App\Http\Resources\Api\V1\PricingRuleVersionResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function index(): JsonResponse
    {
        $versions = PricingRuleVersion::query()->latest()->get();

        return ApiResponse::success(['versions' => PricingRuleVersionResource::collection($versions)]);
    }

    public function store(CreatePricingRuleVersionRequest $request, CreatePricingRuleVersionAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $version = $action->handle($request->validated(), $actor);

        return ApiResponse::success(['version' => new PricingRuleVersionResource($version)], status: 201);
    }

    public function activate(Request $request, PricingRuleVersion $pricingRuleVersion, ActivatePricingRuleVersionAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $version = $action->handle($pricingRuleVersion, $actor);

        return ApiResponse::success(['version' => new PricingRuleVersionResource($version)]);
    }
}
