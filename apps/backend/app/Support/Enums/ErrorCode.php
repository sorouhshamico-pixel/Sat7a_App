<?php

namespace App\Support\Enums;

/**
 * Stable, machine-readable error codes returned in every API error envelope
 * (see docs/API_SPECIFICATION.md). Clients — including future Flutter apps —
 * branch on these, never on the human-readable message text.
 */
enum ErrorCode: string
{
    case ValidationFailed = 'VALIDATION_FAILED';
    case Unauthenticated = 'UNAUTHENTICATED';
    case Unauthorized = 'UNAUTHORIZED';
    case NotFound = 'NOT_FOUND';
    case RateLimited = 'RATE_LIMITED';
    case InternalError = 'INTERNAL_ERROR';

    case AccountSuspended = 'ACCOUNT_SUSPENDED';

    case OtpInvalid = 'OTP_INVALID';
    case OtpExpired = 'OTP_EXPIRED';
    case OtpMaxAttemptsExceeded = 'OTP_MAX_ATTEMPTS_EXCEEDED';
    case OtpSendRateLimited = 'OTP_SEND_RATE_LIMITED';

    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case MfaRequired = 'MFA_REQUIRED';
    case MfaInvalidCode = 'MFA_INVALID_CODE';
    case MfaAlreadyEnabled = 'MFA_ALREADY_ENABLED';
    case MfaNotEnabled = 'MFA_NOT_ENABLED';
    case MfaChallengeExpired = 'MFA_CHALLENGE_EXPIRED';

    case MapsProviderUnavailable = 'MAPS_PROVIDER_UNAVAILABLE';
}
