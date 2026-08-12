<?php

namespace App\Domain\Maps\Exceptions;

use App\Support\Enums\ErrorCode;
use RuntimeException;

/**
 * Thrown by any maps provider (geocoding/places/routing) on failure —
 * never lets the caller crash the request; see docs/SECURITY.md
 * §Error handling and spec §143 "Map failure" (the system must not go
 * down just because the maps vendor is unavailable).
 */
class MapsProviderException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly int $status = 502,
    ) {
        parent::__construct($message);
    }

    public static function unavailable(string $reason): self
    {
        return new self(ErrorCode::MapsProviderUnavailable, "Maps provider unavailable: {$reason}", 502);
    }

    public static function notFound(string $what): self
    {
        return new self(ErrorCode::NotFound, "{$what} not found.", 404);
    }
}
