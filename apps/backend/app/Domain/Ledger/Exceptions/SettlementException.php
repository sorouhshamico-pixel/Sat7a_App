<?php

namespace App\Domain\Ledger\Exceptions;

use App\Support\Enums\ErrorCode;
use RuntimeException;

class SettlementException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function noEligibleEarnings(): self
    {
        return new self(ErrorCode::NoEligibleEarnings, 'This provider has no eligible, unclaimed, past-hold-window earnings to settle.', 422);
    }

    public static function invalidTransition(): self
    {
        return new self(ErrorCode::SettlementInvalidTransition, 'This settlement batch cannot move to that status from its current one.', 422);
    }

    public static function bankAccountNotVerified(): self
    {
        return new self(ErrorCode::BankAccountNotVerified, 'This provider\'s bank account has not been verified — a settlement cannot be marked paid against it.', 422);
    }

    public static function bankAccountNotFound(): self
    {
        return new self(ErrorCode::BankAccountNotFound, 'This provider has no bank account on file.', 404);
    }
}
