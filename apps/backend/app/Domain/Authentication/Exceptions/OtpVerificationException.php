<?php

namespace App\Domain\Authentication\Exceptions;

use App\Support\Enums\ErrorCode;
use RuntimeException;

class OtpVerificationException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function invalidCode(): self
    {
        return new self(ErrorCode::OtpInvalid, 'The verification code is incorrect.');
    }

    public static function expired(): self
    {
        return new self(ErrorCode::OtpExpired, 'The verification code has expired. Request a new one.');
    }

    public static function maxAttemptsExceeded(): self
    {
        return new self(ErrorCode::OtpMaxAttemptsExceeded, 'Too many incorrect attempts. Request a new code.', 429);
    }

    public static function accountNotProvisioned(): self
    {
        return new self(
            ErrorCode::NotFound,
            'No account exists for this phone number yet. Ask your provider or an administrator to add you first.',
            404,
        );
    }

    public static function accountSuspended(): self
    {
        return new self(ErrorCode::AccountSuspended, 'This account has been suspended.', 403);
    }
}
