<?php

namespace App\Domain\Fleet\Exceptions;

use App\Support\Enums\ErrorCode;
use RuntimeException;

class FleetException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
