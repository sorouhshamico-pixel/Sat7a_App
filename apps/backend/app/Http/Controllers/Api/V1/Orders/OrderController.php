<?php

namespace App\Http\Controllers\Api\V1\Orders;

use App\Domain\Maps\DataTransferObjects\Coordinates;
use App\Domain\Maps\Exceptions\MapsProviderException;
use App\Domain\Orders\Actions\CancelOrderAction;
use App\Domain\Orders\Actions\CreateOrderAction;
use App\Domain\Orders\Enums\OrderCancelledBy;
use App\Domain\Orders\Exceptions\OrderException;
use App\Domain\Pricing\Exceptions\PricingException;
use App\Domain\Tracking\Actions\GetOrderLocationAction;
use App\Http\Controllers\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\CancelOrderRequest;
use App\Http\Requests\Api\V1\Orders\CreateOrderRequest;
use App\Http\Resources\Api\V1\OrderLocationPingResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Resources\Api\V1\OrderStatusHistoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Support\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ResolvesCustomer;

    public function index(Request $request): JsonResponse
    {
        // Real Phase 24 finding: this list endpoint omitted `vehicle`
        // entirely from every order (OrderResource's whenLoaded('vehicle',
        // ...) silently returns nothing when it isn't eager-loaded) while
        // show() below always has — an inconsistency, not by design. No
        // customer-facing screen happened to render the field yet (see
        // docs/PERFORMANCE.md), but the API's own contract promises it.
        $orders = $this->resolveCustomer($request)->orders()
            ->with('vehicle')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            data: ['orders' => OrderResource::collection($orders->items())],
            meta: [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        );
    }

    public function store(CreateOrderRequest $request, CreateOrderAction $action): JsonResponse
    {
        if ($request->boolean('requires_manual_quote')) {
            return ApiResponse::error(
                ErrorCode::ManualQuoteRequired,
                'This situation needs a manual quote — please contact support to place this order.',
                422,
            );
        }

        $customer = $this->resolveCustomer($request);

        $vehicle = $customer->vehicles()->where('public_id', $request->string('vehicle_id')->toString())->first();

        if ($vehicle === null) {
            return ApiResponse::error(ErrorCode::VehicleNotFound, 'Vehicle not found.', 404);
        }

        try {
            $order = $action->handle(
                customer: $customer,
                vehicle: $vehicle,
                serviceType: $request->string('service_type')->toString(),
                vehicleCategory: $request->string('vehicle_category')->toString(),
                pickup: new Coordinates($request->float('pickup_latitude'), $request->float('pickup_longitude')),
                pickupFormattedAddress: $request->string('pickup_formatted_address')->toString(),
                dropoff: new Coordinates($request->float('dropoff_latitude'), $request->float('dropoff_longitude')),
                dropoffFormattedAddress: $request->string('dropoff_formatted_address')->toString(),
                notes: $request->string('notes')->toString() ?: null,
            );
        } catch (MapsProviderException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        } catch (PricingException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['order' => new OrderResource($order->load('vehicle'))], status: 201);
    }

    public function show(Request $request, string $orderPublicId): JsonResponse
    {
        $order = $this->resolveCustomer($request)->orders()
            ->where('public_id', $orderPublicId)
            ->with(['vehicle', 'assignedProvider', 'assignedDriver.user', 'assignedTowTruck'])
            ->first();

        if ($order === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Order not found.', 404);
        }

        return ApiResponse::success(['order' => new OrderResource($order)]);
    }

    public function timeline(Request $request, string $orderPublicId): JsonResponse
    {
        $order = $this->resolveCustomer($request)->orders()->where('public_id', $orderPublicId)->first();

        if ($order === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Order not found.', 404);
        }

        return ApiResponse::success([
            'timeline' => OrderStatusHistoryResource::collection($order->statusHistory),
        ]);
    }

    public function location(Request $request, string $orderPublicId, GetOrderLocationAction $action): JsonResponse
    {
        $order = $this->resolveCustomer($request)->orders()
            ->where('public_id', $orderPublicId)
            ->with('assignedTowTruck')
            ->first();

        if ($order === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Order not found.', 404);
        }

        $location = $action->handle($order, $request->integer('limit', 200));

        return ApiResponse::success([
            'current' => $location['current'],
            'path' => OrderLocationPingResource::collection($location['path']),
        ]);
    }

    public function cancel(CancelOrderRequest $request, string $orderPublicId, CancelOrderAction $action): JsonResponse
    {
        $order = $this->resolveCustomer($request)->orders()->where('public_id', $orderPublicId)->first();

        if ($order === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Order not found.', 404);
        }

        /** @var User $actor */
        $actor = $request->user();

        try {
            $order = $action->handle(
                order: $order,
                cancelledBy: OrderCancelledBy::Customer,
                actor: $actor,
                reason: $request->string('reason')->toString() ?: null,
            );
        } catch (OrderException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['order' => new OrderResource($order)]);
    }
}
