<?php

namespace App\Domain\Payments\Exceptions;

use App\Support\Enums\ErrorCode;
use RuntimeException;

class PaymentException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function orderNotPayable(): self
    {
        return new self(ErrorCode::OrderNotPayable, 'This order cannot be paid for in its current state.', 422);
    }

    public static function paymentAlreadyActive(): self
    {
        return new self(ErrorCode::PaymentAlreadyActive, 'A payment for this order is already pending or has already succeeded.', 409);
    }

    public static function invalidTransition(): self
    {
        return new self(ErrorCode::PaymentInvalidTransition, 'This payment cannot move to that status from its current one.', 422);
    }

    public static function notRefundable(): self
    {
        return new self(ErrorCode::PaymentNotRefundable, 'This payment is not in a refundable state.', 422);
    }

    public static function refundExceedsAvailableAmount(): self
    {
        return new self(ErrorCode::RefundExceedsAvailableAmount, 'The refund amount exceeds what remains available on this payment.', 422);
    }

    public static function webhookSignatureInvalid(): self
    {
        return new self(ErrorCode::WebhookSignatureInvalid, 'Webhook signature verification failed.', 401);
    }

    public static function malformedWebhookPayload(): self
    {
        return new self(ErrorCode::WebhookSignatureInvalid, 'Webhook payload is malformed.', 400);
    }

    public static function unknownGateway(string $gateway): self
    {
        return new self(ErrorCode::NotFound, "Unknown payment gateway \"{$gateway}\".", 404);
    }
}
