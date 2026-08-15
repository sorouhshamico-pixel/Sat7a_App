<?php

namespace App\Domain\Disputes\Exceptions;

use App\Support\Enums\ErrorCode;
use RuntimeException;

class DisputeException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function orderNotDisputable(): self
    {
        return new self(ErrorCode::OrderNotDisputable, 'Only a completed or cancelled order can be disputed.', 422);
    }

    public static function alreadyOpen(): self
    {
        return new self(ErrorCode::DisputeAlreadyOpen, 'This order already has an open dispute.', 422);
    }

    public static function invalidTransition(): self
    {
        return new self(ErrorCode::DisputeInvalidTransition, 'This dispute cannot move to that status from its current one.', 422);
    }

    public static function resolutionNotesRequired(): self
    {
        return new self(ErrorCode::DisputeResolutionNotesRequired, 'Resolution notes are required to resolve or reject a dispute.', 422);
    }
}
