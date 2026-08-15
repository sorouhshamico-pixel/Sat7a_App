<?php

namespace App\Domain\Notifications\Enums;

/**
 * Additive only, like App\Domain\Authorization\Enums\PermissionName — grows
 * as later phases wire up more events. See docs/NOTIFICATIONS.md.
 */
enum NotificationType: string
{
    case OrderCreated = 'order_created';
    case OrderStatusUpdated = 'order_status_updated';
    case OrderCancelled = 'order_cancelled';
    case DocumentExpiring = 'document_expiring';
    case DocumentExpired = 'document_expired';
}
