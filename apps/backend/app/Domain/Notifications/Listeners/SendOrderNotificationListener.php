<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Actions\SendNotificationAction;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderCancelled;
use App\Domain\Orders\Events\OrderCreated;
use App\Domain\Orders\Events\OrderStatusChanged;
use App\Models\User;

/**
 * Real customer/provider notification delivery for order lifecycle events
 * — see docs/NOTIFICATIONS.md and docs/ORDER_LIFECYCLE.md. Replaces
 * App\Domain\Orders\Listeners\LogOrderLifecycleListener's role as "the
 * thing that stands in for real notifications" now that this domain
 * exists; that listener still runs too, for structured log/monitoring
 * purposes, which is a distinct concern from user-facing delivery.
 */
class SendOrderNotificationListener
{
    /**
     * Only these statuses are milestone-worthy enough to notify a
     * customer about — the granular in-between states (vehicle_loading,
     * trip_started, in_transit, vehicle_delivered) are covered by live
     * tracking (Phase 11), not a push/SMS interruption.
     */
    private const MILESTONE_TRANSLATION_KEYS = [
        OrderStatus::ProviderAssigned->value => 'order_status_provider_assigned',
        OrderStatus::ProviderEnRoute->value => 'order_status_provider_en_route',
        OrderStatus::ProviderArrived->value => 'order_status_provider_arrived',
        OrderStatus::Completed->value => 'order_status_completed',
    ];

    public function __construct(private readonly SendNotificationAction $action) {}

    public function handleCreated(OrderCreated $event): void
    {
        $customer = $event->order->customer;

        $this->notify(
            $customer->user,
            NotificationType::OrderCreated,
            'order_created',
            ['order_id' => $event->order->public_id],
        );
    }

    public function handleStatusChanged(OrderStatusChanged $event): void
    {
        $key = self::MILESTONE_TRANSLATION_KEYS[$event->to->value] ?? null;

        if ($key === null) {
            return;
        }

        $this->notify(
            $event->order->customer->user,
            NotificationType::OrderStatusUpdated,
            $key,
            ['order_id' => $event->order->public_id, 'status' => $event->to->value],
        );
    }

    public function handleCancelled(OrderCancelled $event): void
    {
        $order = $event->order;

        $this->notify(
            $order->customer->user,
            NotificationType::OrderCancelled,
            'order_cancelled_customer',
            ['order_id' => $order->public_id],
        );

        $driver = $order->assignedDriver;

        if ($driver !== null) {
            $this->notify(
                $driver->user,
                NotificationType::OrderCancelled,
                'order_cancelled_driver',
                ['order_id' => $order->public_id],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function notify(User $recipient, NotificationType $type, string $translationKey, array $params): void
    {
        $locale = $recipient->locale ?? config('app.locale');

        $this->action->handle(
            recipient: $recipient,
            type: $type,
            title: __("notifications.{$translationKey}_title", $params, $locale),
            body: __("notifications.{$translationKey}_body", $params, $locale),
            data: $params,
        );
    }
}
