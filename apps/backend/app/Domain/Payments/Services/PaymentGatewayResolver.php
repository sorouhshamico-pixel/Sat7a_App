<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Adapters\Fake\FakePaymentGateway;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Exceptions\PaymentException;

/**
 * The webhook endpoint is reached at `/webhooks/payments/{gateway}` — the
 * gateway that's actually calling us is named in the URL, not whatever
 * `PAYMENT_GATEWAY_DRIVER` currently defaults to (see
 * App\Providers\PaymentServiceProvider), so it needs its own by-name
 * lookup rather than reusing the container binding.
 */
class PaymentGatewayResolver
{
    /**
     * @throws PaymentException
     */
    public function resolve(string $gatewayName): PaymentGateway
    {
        return match ($gatewayName) {
            'fake' => new FakePaymentGateway,
            default => throw PaymentException::unknownGateway($gatewayName),
        };
    }
}
