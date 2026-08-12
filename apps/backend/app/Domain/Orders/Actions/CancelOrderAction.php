<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Enums\OrderCancelledBy;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderCancelled;
use App\Domain\Orders\Exceptions\OrderException;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderStateMachine;
use App\Models\User;

class CancelOrderAction
{
    public function __construct(private readonly OrderStateMachine $stateMachine) {}

    /**
     * @throws OrderException
     */
    public function handle(Order $order, OrderCancelledBy $cancelledBy, ?User $actor, ?string $reason = null): Order
    {
        if ($cancelledBy === OrderCancelledBy::Customer && ! $order->status->isCustomerCancellable()) {
            throw OrderException::notCustomerCancellable($order->status);
        }

        $targetStatus = match ($cancelledBy) {
            OrderCancelledBy::Customer => OrderStatus::CancelledByCustomer,
            OrderCancelledBy::Provider => OrderStatus::CancelledByProvider,
            OrderCancelledBy::Admin, OrderCancelledBy::System => OrderStatus::CancelledByAdmin,
        };

        $order = $this->stateMachine->transition($order, $targetStatus, $actor, $reason);

        $order->cancelled_by = $cancelledBy;
        $order->cancellation_reason = $reason;
        $order->cancelled_at = now();
        $order->save();

        OrderCancelled::dispatch($order, $cancelledBy, $reason);

        return $order;
    }
}
