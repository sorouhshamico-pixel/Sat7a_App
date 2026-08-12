<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Dispatch\Actions\DispatchOrderAction;
use App\Domain\Dispatch\Actions\ManuallyAssignOrderAction;
use App\Domain\Dispatch\Exceptions\DispatchException;
use App\Domain\Fleet\Models\TowTruck;
use App\Domain\Orders\Models\Order;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignOrderRequest;
use App\Http\Resources\Api\V1\DispatchOfferResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Support\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;

class DispatchController extends Controller
{
    public function offers(Order $order): JsonResponse
    {
        $offers = $order->dispatchOffers()->latest()->get();

        return ApiResponse::success(['offers' => DispatchOfferResource::collection($offers)]);
    }

    public function retry(Order $order, DispatchOrderAction $action): JsonResponse
    {
        // Rescans from wave 1 — trucks already offered are still
        // excluded (tracked per-order, not per-wave), so this is safe to
        // call after new trucks have come online since the last attempt.
        try {
            $action->handle($order, 1);
        } catch (DispatchException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['order' => new OrderResource($order->fresh(['assignedProvider', 'assignedDriver.user', 'assignedTowTruck']))]);
    }

    public function assign(AssignOrderRequest $request, Order $order, ManuallyAssignOrderAction $action): JsonResponse
    {
        $towTruck = TowTruck::query()->where('public_id', $request->string('tow_truck_id')->toString())->first();

        if ($towTruck === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Tow truck not found.', 404);
        }

        /** @var User $actor */
        $actor = $request->user();

        try {
            $order = $action->handle($order, $towTruck, $actor, $request->string('reason')->toString() ?: null);
        } catch (DispatchException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['order' => new OrderResource($order->load(['assignedProvider', 'assignedDriver.user', 'assignedTowTruck']))]);
    }
}
