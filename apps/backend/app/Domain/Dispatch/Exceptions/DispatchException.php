<?php

namespace App\Domain\Dispatch\Exceptions;

use App\Support\Enums\ErrorCode;
use RuntimeException;

class DispatchException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function orderNotDispatchable(): self
    {
        return new self(ErrorCode::OrderNotDispatchable, 'This order is not in a state that can be dispatched.', 422);
    }

    public static function offerNoLongerAvailable(): self
    {
        return new self(ErrorCode::DispatchOfferNoLongerAvailable, 'This offer is no longer available.', 409);
    }

    public static function towTruckNotEligible(): self
    {
        return new self(ErrorCode::TowTruckNotEligible, 'This tow truck is not eligible to be assigned to this order.', 422);
    }
}
