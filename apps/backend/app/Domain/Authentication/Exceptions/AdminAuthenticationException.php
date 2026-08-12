<?php

namespace App\Domain\Authentication\Exceptions;

use App\Support\Enums\ErrorCode;
use RuntimeException;

class AdminAuthenticationException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function invalidCredentials(): self
    {
        return new self(ErrorCode::InvalidCredentials, 'The provided credentials are incorrect.', 401);
    }

    public static function accountSuspended(): self
    {
        return new self(ErrorCode::AccountSuspended, 'This account has been suspended.', 403);
    }

    public static function mfaChallengeExpired(): self
    {
        return new self(ErrorCode::MfaChallengeExpired, 'This login challenge has expired. Log in again.', 401);
    }

    public static function mfaInvalidCode(): self
    {
        return new self(ErrorCode::MfaInvalidCode, 'The authentication code is incorrect.', 422);
    }

    public static function mfaAlreadyEnabled(): self
    {
        return new self(ErrorCode::MfaAlreadyEnabled, 'Two-factor authentication is already enabled.', 422);
    }

    public static function mfaNotEnabled(): self
    {
        return new self(ErrorCode::MfaNotEnabled, 'Two-factor authentication is not enabled.', 422);
    }
}
