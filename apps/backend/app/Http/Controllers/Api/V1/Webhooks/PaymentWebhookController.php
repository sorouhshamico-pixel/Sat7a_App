<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Domain\Payments\Actions\ProcessPaymentWebhookAction;
use App\Domain\Payments\Exceptions\PaymentException;
use App\Domain\Payments\Services\PaymentGatewayResolver;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public — the gateway itself calls this, so it can never require a
 * Sanctum token. Trust is established entirely by
 * App\Domain\Payments\Contracts\PaymentGateway::verifyWebhookSignature()
 * instead (see docs/PAYMENT_ARCHITECTURE.md §Webhooks).
 */
class PaymentWebhookController extends Controller
{
    public function handle(
        Request $request,
        string $gateway,
        PaymentGatewayResolver $resolver,
        ProcessPaymentWebhookAction $action,
    ): JsonResponse {
        try {
            $gatewayAdapter = $resolver->resolve($gateway);
            $action->handle($gatewayAdapter, $gateway, $request);
        } catch (PaymentException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status);
        }

        return ApiResponse::success(['received' => true]);
    }
}
