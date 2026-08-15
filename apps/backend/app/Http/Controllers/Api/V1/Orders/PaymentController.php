<?php

namespace App\Http\Controllers\Api\V1\Orders;

use App\Domain\Payments\Actions\CreatePaymentAction;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Exceptions\PaymentException;
use App\Http\Controllers\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\CreatePaymentRequest;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Http\Responses\ApiResponse;
use App\Support\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ResolvesCustomer;

    public function index(Request $request, string $orderPublicId): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $order = $customer->orders()->where('public_id', $orderPublicId)->first();

        if ($order === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Order not found.', 404);
        }

        return ApiResponse::success([
            'payments' => PaymentResource::collection($order->payments()->latest()->get()),
        ]);
    }

    public function store(CreatePaymentRequest $request, string $orderPublicId, CreatePaymentAction $action): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $order = $customer->orders()->where('public_id', $orderPublicId)->first();

        if ($order === null) {
            return ApiResponse::error(ErrorCode::NotFound, 'Order not found.', 404);
        }

        try {
            $initiation = $action->handle(
                order: $order,
                customer: $customer,
                method: PaymentMethod::from($request->string('method')->toString()),
                idempotencyKey: $request->header('Idempotency-Key'),
            );
        } catch (PaymentException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success([
            'payment' => new PaymentResource($initiation->payment),
            'redirect_url' => $initiation->redirectUrl,
        ], status: 201);
    }
}
