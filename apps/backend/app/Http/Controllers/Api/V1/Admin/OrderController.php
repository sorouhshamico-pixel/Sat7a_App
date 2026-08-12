<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Orders\Actions\CancelOrderAction;
use App\Domain\Orders\Enums\OrderCancelledBy;
use App\Domain\Orders\Exceptions\OrderException;
use App\Domain\Orders\Models\Order;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReasonRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()->with('vehicle')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $orders = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            data: ['orders' => OrderResource::collection($orders->items())],
            meta: [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        );
    }

    public function show(Order $order): JsonResponse
    {
        return ApiResponse::success(['order' => new OrderResource($order->load('vehicle'))]);
    }

    public function cancel(ReasonRequest $request, Order $order, CancelOrderAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $order = $action->handle(
                order: $order,
                cancelledBy: OrderCancelledBy::Admin,
                actor: $actor,
                reason: $request->string('reason')->toString(),
            );
        } catch (OrderException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['order' => new OrderResource($order)]);
    }
}
