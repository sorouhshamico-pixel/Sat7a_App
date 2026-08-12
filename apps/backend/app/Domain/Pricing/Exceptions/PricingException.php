<?php

namespace App\Domain\Pricing\Exceptions;

use App\Support\Enums\ErrorCode;
use RuntimeException;

class PricingException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function noActiveVersion(): self
    {
        return new self(ErrorCode::PricingUnavailable, 'No active pricing rules are configured.', 503);
    }
}
