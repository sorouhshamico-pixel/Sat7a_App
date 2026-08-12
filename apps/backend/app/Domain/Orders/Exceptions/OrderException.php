<?php

namespace App\Domain\Orders\Exceptions;

use App\Domain\Orders\Enums\OrderStatus;
use App\Support\Enums\ErrorCode;
use RuntimeException;

class OrderException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function invalidTransition(OrderStatus $from, OrderStatus $to): self
    {
        return new self(
            ErrorCode::OrderInvalidTransition,
            "Cannot transition an order from \"{$from->value}\" to \"{$to->value}\".",
        );
    }

    public static function notCustomerCancellable(OrderStatus $current): self
    {
        return new self(
            ErrorCode::OrderNotCancellable,
            "An order in status \"{$current->value}\" can no longer be cancelled by the customer.",
        );
    }
}
