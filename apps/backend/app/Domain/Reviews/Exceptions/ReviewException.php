<?php

namespace App\Domain\Reviews\Exceptions;

use App\Support\Enums\ErrorCode;
use RuntimeException;

class ReviewException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function orderNotReviewable(): self
    {
        return new self(ErrorCode::OrderNotReviewable, 'Only a completed order can be reviewed.', 422);
    }

    public static function alreadyReviewed(): self
    {
        return new self(ErrorCode::ReviewAlreadyExists, 'This order has already been reviewed.', 422);
    }
}
