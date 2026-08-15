<?php

namespace App\Http\Controllers\Api\V1\Drivers;

use App\Domain\Orders\Actions\AdvanceTripStatusAction;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Exceptions\OrderException;
use App\Http\Controllers\Concerns\ResolvesDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Drivers\AdvanceTripStatusRequest;
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
}
