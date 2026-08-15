<?php

namespace App\Domain\Payments\DataTransferObjects;

use App\Domain\Payments\Enums\RefundStatus;

readonly class RefundGatewayResult
{
    public function __construct(
        public ?string $gatewayRefundId,
        public RefundStatus $status,
        public ?string $failureReason = null,
    ) {}
}
