<?php

namespace App\Domain\Orders\Listeners;

use App\Domain\Orders\Events\OrderCancelled;
use App\Domain\Orders\Events\OrderCreated;
use Illuminate\Support\Facades\Log;

/**
 * Structured logging only, matching the `order.accepted` shape documented
 * in docs/MONITORING.md. Stands in for real customer/provider
 * notifications until the Notifications domain lands in Phase 16 — see
 * docs/ROADMAP.md.
 */
class LogOrderLifecycleListener
{
    public function handleCreated(OrderCreated $event): void
    {
        Log::info('order.created', [
            'order_id' => $event->order->public_id,
            'customer_id' => $event->order->customer->public_id,
            'service_type' => $event->order->service_type,
            'quoted_price' => $event->order->quoted_price,
        ]);
    }

    public function handleCancelled(OrderCancelled $event): void
    {
        Log::info('order.cancelled', [
            'order_id' => $event->order->public_id,
            'cancelled_by' => $event->cancelledBy->value,
            'reason' => $event->reason,
        ]);
    }
}
