<?php

namespace App\Domain\Customers\Exceptions;

use App\Support\Enums\ErrorCode;
use RuntimeException;

class CustomerException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
