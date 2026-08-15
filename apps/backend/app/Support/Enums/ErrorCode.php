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

    case PricingUnavailable = 'PRICING_UNAVAILABLE';
    case ManualQuoteRequired = 'MANUAL_QUOTE_REQUIRED';

    case OrderInvalidTransition = 'ORDER_INVALID_TRANSITION';
    case OrderNotCancellable = 'ORDER_NOT_CANCELLABLE';
    case VehicleNotFound = 'VEHICLE_NOT_FOUND';

    case OrderNotDispatchable = 'ORDER_NOT_DISPATCHABLE';
    case DispatchOfferNoLongerAvailable = 'DISPATCH_OFFER_NO_LONGER_AVAILABLE';
    case TowTruckNotEligible = 'TOW_TRUCK_NOT_ELIGIBLE';

    case OrderNotPayable = 'ORDER_NOT_PAYABLE';
    case PaymentAlreadyActive = 'PAYMENT_ALREADY_ACTIVE';
    case PaymentInvalidTransition = 'PAYMENT_INVALID_TRANSITION';
    case PaymentNotRefundable = 'PAYMENT_NOT_REFUNDABLE';
    case RefundExceedsAvailableAmount = 'REFUND_EXCEEDS_AVAILABLE_AMOUNT';
    case WebhookSignatureInvalid = 'WEBHOOK_SIGNATURE_INVALID';

    case NoEligibleEarnings = 'NO_ELIGIBLE_EARNINGS';
    case SettlementInvalidTransition = 'SETTLEMENT_INVALID_TRANSITION';
    case BankAccountNotVerified = 'BANK_ACCOUNT_NOT_VERIFIED';
    case BankAccountNotFound = 'BANK_ACCOUNT_NOT_FOUND';

    case OrderNotReviewable = 'ORDER_NOT_REVIEWABLE';
    case ReviewAlreadyExists = 'REVIEW_ALREADY_EXISTS';

    case OrderNotDisputable = 'ORDER_NOT_DISPUTABLE';
    case DisputeAlreadyOpen = 'DISPUTE_ALREADY_OPEN';
    case DisputeInvalidTransition = 'DISPUTE_INVALID_TRANSITION';
    case DisputeResolutionNotesRequired = 'DISPUTE_RESOLUTION_NOTES_REQUIRED';
}
