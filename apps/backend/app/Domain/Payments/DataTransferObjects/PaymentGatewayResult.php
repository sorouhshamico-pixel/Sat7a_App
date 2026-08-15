<?php

namespace App\Domain\Payments\DataTransferObjects;

use App\Domain\Payments\Enums\PaymentStatus;

readonly class PaymentGatewayResult
{
    public function __construct(
        public string $gatewayPaymentId,
        public PaymentStatus $status,
        public ?string $redirectUrl = null,
        public ?string $cardBrand = null,
        public ?string $cardLastFour = null,
        public ?string $failureReason = null,
    ) {}
}
