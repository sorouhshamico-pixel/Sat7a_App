<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Payments\Actions\RefundPaymentAction;
use App\Domain\Payments\Exceptions\PaymentException;
use App\Domain\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\RefundPaymentRequest;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Http\Resources\Api\V1\RefundResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()->with('order')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $payments = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            data: ['payments' => PaymentResource::collection($payments->items())],
            meta: [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        );
    }

    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['order', 'refunds']);

        return ApiResponse::success([
            'payment' => new PaymentResource($payment),
            'refunds' => RefundResource::collection($payment->refunds),
        ]);
    }

    public function refund(RefundPaymentRequest $request, Payment $payment, RefundPaymentAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $refund = $action->handle(
                payment: $payment,
                amountMinorUnits: $request->integer('amount'),
                actor: $actor,
                reason: $request->string('reason')->toString() ?: null,
            );
        } catch (PaymentException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success([
            'refund' => new RefundResource($refund),
            'payment' => new PaymentResource($payment->fresh()),
        ]);
    }
}
