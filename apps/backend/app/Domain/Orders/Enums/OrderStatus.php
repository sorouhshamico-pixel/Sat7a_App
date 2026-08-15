<?php

namespace App\Domain\Orders\Enums;

/**
 * See docs/ORDER_LIFECYCLE.md. Every transition — now and in every later
 * phase (dispatch, trip tracking, disputes, refunds) — goes through
 * App\Domain\Orders\Services\OrderStateMachine, which checks this matrix;
 * no code sets `orders.status` directly.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case SearchingProvider = 'searching_provider';
    case ProviderAssigned = 'provider_assigned';
    case ProviderEnRoute = 'provider_en_route';
    case ProviderArrived = 'provider_arrived';
    case VehicleLoading = 'vehicle_loading';
    case TripStarted = 'trip_started';
    case InTransit = 'in_transit';
    case VehicleDelivered = 'vehicle_delivered';
    case Completed = 'completed';
    case CancelledByCustomer = 'cancelled_by_customer';
    case CancelledByProvider = 'cancelled_by_provider';
    case CancelledByAdmin = 'cancelled_by_admin';
    case Expired = 'expired';
    case Disputed = 'disputed';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::SearchingProvider, self::CancelledByCustomer, self::CancelledByAdmin, self::Expired],
            self::SearchingProvider => [self::ProviderAssigned, self::CancelledByCustomer, self::CancelledByAdmin, self::Expired],
            self::ProviderAssigned => [self::ProviderEnRoute, self::CancelledByCustomer, self::CancelledByProvider, self::CancelledByAdmin],
            self::ProviderEnRoute => [self::ProviderArrived, self::CancelledByCustomer, self::CancelledByProvider, self::CancelledByAdmin],
            self::ProviderArrived => [self::VehicleLoading, self::CancelledByCustomer, self::CancelledByProvider, self::CancelledByAdmin],
            self::VehicleLoading => [self::TripStarted, self::CancelledByProvider, self::CancelledByAdmin],
            self::TripStarted => [self::InTransit],
            self::InTransit => [self::VehicleDelivered],
            self::VehicleDelivered => [self::Completed],
            self::Completed => [self::Disputed],
            self::CancelledByCustomer => [self::RefundPending],
            self::CancelledByProvider => [self::RefundPending],
            self::CancelledByAdmin => [self::RefundPending],
            self::Expired => [],
            self::Disputed => [self::RefundPending, self::Completed],
            self::RefundPending => [self::Refunded],
            self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Statuses a customer may self-service-cancel from — never once a
     * trip is physically underway (`trip_started` onward), since the
     * vehicle may already be on the truck (see docs/ORDER_LIFECYCLE.md
     * §Cancellation).
     *
     * @return list<self>
     */
    public static function customerCancellable(): array
    {
        return [
            self::Pending,
            self::SearchingProvider,
            self::ProviderAssigned,
            self::ProviderEnRoute,
            self::ProviderArrived,
            self::VehicleLoading,
        ];
    }

    public function isCustomerCancellable(): bool
    {
        return in_array($this, self::customerCancellable(), true);
    }

    /**
     * Whether a payment can be created for an order in this status — see
     * docs/PAYMENT_ARCHITECTURE.md and docs/PRODUCT_REQUIREMENTS.md
     * ("Trip completes; customer pays"). Deliberately narrow: payment is
     * a post-service concern, not a pre-authorization/deposit one — no
     * such flow has been asked for.
     */
    public function isPayable(): bool
    {
        return in_array($this, [self::VehicleDelivered, self::Completed], true);
    }
}
