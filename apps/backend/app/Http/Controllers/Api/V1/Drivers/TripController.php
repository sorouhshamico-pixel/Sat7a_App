<?php

namespace App\Http\Controllers\Api\V1\Drivers;

use App\Domain\Orders\Actions\AdvanceTripStatusAction;
use App\Domain\Orders\Actions\CancelOrderAction;
use App\Domain\Orders\Enums\OrderCancelledBy;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Exceptions\OrderException;
use App\Http\Controllers\Concerns\ResolvesDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Drivers\AdvanceTripStatusRequest;
use App\Http\Requests\Api\V1\Orders\CancelOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Support\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;

class TripController extends Controller
{
    use ResolvesDriver;

    public function advance(AdvanceTripStatusRequest $request, string $orderPublicId, AdvanceTripStatusAction $action): JsonResponse
    {
        $driver = $this->resolveDriver($request);

        $order = $driver->assignedOrders()->where('public_id', $orderPublicId)->first();

        if ($order === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Order not found.', 404);
        }

        /** @var User $actor */
        $actor = $request->user();

        try {
            $order = $action->handle($order, OrderStatus::from($request->string('status')->toString()), $actor);
        } catch (OrderException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['order' => new OrderResource($order)]);
    }

    /**
     * `OrderCancelledBy::Provider` and `CancelOrderAction`'s handling of it
     * (targeting `OrderStatus::CancelledByProvider`, releasing a reserved
     * tow truck back to available) have existed since Phase 8 — this was
     * the one enum case with no route exposing it at all (see
     * docs/ORDER_LIFECYCLE.md and docs/LIVE_LOCATION_TRACKING.md). The
     * state machine itself (not this controller) is what rejects a
     * cancellation once the trip has actually started
     * (`OrderStatus::TripStarted` has no `CancelledByProvider` transition).
     */
    public function cancel(CancelOrderRequest $request, string $orderPublicId, CancelOrderAction $action): JsonResponse
    {
        $driver = $this->resolveDriver($request);

        $order = $driver->assignedOrders()->where('public_id', $orderPublicId)->first();

        if ($order === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Order not found.', 404);
        }

        /** @var User $actor */
        $actor = $request->user();

        try {
            $order = $action->handle(
                order: $order,
                cancelledBy: OrderCancelledBy::Provider,
                actor: $actor,
                reason: $request->string('reason')->toString() ?: null,
            );
        } catch (OrderException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['order' => new OrderResource($order)]);
    }
}
