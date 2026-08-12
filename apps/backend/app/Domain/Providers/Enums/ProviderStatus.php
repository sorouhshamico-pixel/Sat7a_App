<?php

namespace App\Domain\Providers\Enums;

/**
 * See docs/COMPLIANCE.md §Provider compliance lifecycle. A provider is
 * never auto-activated — every transition into `approved` is a deliberate
 * compliance action.
 */
enum ProviderStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Inactive = 'inactive';

    public function canReceiveOrders(): bool
    {
        return $this === self::Approved;
    }
}
