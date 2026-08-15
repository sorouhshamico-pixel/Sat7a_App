<?php

namespace App\Domain\Payments\Enums;

/**
 * See docs/PAYMENT_ARCHITECTURE.md §Payment states. Order status and
 * payment status are related but not the same state machine — an order
 * never flips to "paid" from a frontend redirect alone, only from gateway/
 * webhook confirmation transitioning a Payment through this matrix (see
 * App\Domain\Payments\Actions\ProcessPaymentWebhookAction).
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Authorized, self::Captured, self::Failed, self::Cancelled],
            self::Authorized => [self::Captured, self::Failed, self::Cancelled],
            self::Captured => [self::PartiallyRefunded, self::Refunded],
            self::PartiallyRefunded => [self::PartiallyRefunded, self::Refunded],
            self::Failed, self::Cancelled, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isRefundable(): bool
    {
        return in_array($this, [self::Captured, self::PartiallyRefunded], true);
    }
}
