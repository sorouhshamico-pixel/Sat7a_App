<?php

namespace App\Domain\Payments\DataTransferObjects;

use App\Domain\Payments\Enums\PaymentStatus;

readonly class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public string $gatewayPaymentId,
        public PaymentStatus $status,
        public array $rawPayload,
        public ?string $failureReason = null,
        public ?string $cardBrand = null,
        public ?string $cardLastFour = null,
    ) {}
}
