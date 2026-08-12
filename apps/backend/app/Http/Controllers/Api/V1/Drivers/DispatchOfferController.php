<?php

namespace App\Http\Controllers\Api\V1\Drivers;

use App\Domain\Dispatch\Actions\AcceptDispatchOfferAction;
use App\Domain\Dispatch\Actions\RejectDispatchOfferAction;
use App\Domain\Dispatch\Enums\DispatchOfferStatus;
use App\Domain\Dispatch\Exceptions\DispatchException;
use App\Http\Controllers\Concerns\ResolvesDriver;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DispatchOfferResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Support\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispatchOfferController extends Controller
{
    use ResolvesDriver;

    public function index(Request $request): JsonResponse
    {
        $driver = $this->resolveDriver($request);

        $offers = $driver->dispatchOffers()
            ->where('status', DispatchOfferStatus::Pending)
            ->where('expires_at', '>', now())
            ->with('order')
            ->latest()
            ->get();

        return ApiResponse::success(['offers' => DispatchOfferResource::collection($offers)]);
    }

    public function accept(Request $request, string $offerPublicId, AcceptDispatchOfferAction $action): JsonResponse
    {
        $driver = $this->resolveDriver($request);

        $offer = $driver->dispatchOffers()->where('public_id', $offerPublicId)->first();

        if ($offer === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Offer not found.', 404);
        }

        /** @var User $actor */
        $actor = $request->user();

        try {
            $order = $action->handle($offer, $actor);
        } catch (DispatchException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['order' => new OrderResource($order->load(['assignedProvider', 'assignedDriver.user', 'assignedTowTruck']))]);
    }

    public function reject(Request $request, string $offerPublicId, RejectDispatchOfferAction $action): JsonResponse
    {
        $driver = $this->resolveDriver($request);

        $offer = $driver->dispatchOffers()->where('public_id', $offerPublicId)->first();

        if ($offer === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Offer not found.', 404);
        }

        try {
            $action->handle($offer);
        } catch (DispatchException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['message' => 'Offer declined.']);
    }
}
