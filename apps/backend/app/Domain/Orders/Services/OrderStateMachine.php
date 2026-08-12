<?php

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderStatusChanged;
use App\Domain\Orders\Exceptions\OrderException;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single place every order status change goes through — now and in
 * every later phase (dispatch, trip tracking, disputes, refunds). Never
 * set `order->status` directly; always call transition(), which validates
 * against App\Domain\Orders\Enums\OrderStatus::allowedTransitions() and
 * records the change atomically (see docs/ORDER_LIFECYCLE.md).
 */
class OrderStateMachine
{
    /**
     * @throws OrderException
     */
    public function transition(Order $order, OrderStatus $to, ?User $actor = null, ?string $notes = null): Order
    {
        $from = $order->status;

        if (! $from->canTransitionTo($to)) {
            throw OrderException::invalidTransition($from, $to);
        }

        DB::transaction(function () use ($order, $from, $to, $actor, $notes): void {
            $order->status = $to;
            $order->save();

            $history = new OrderStatusHistory([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $actor?->id,
                'notes' => $notes,
            ]);
            $history->save();
        });

        $order = $order->refresh();

        // ShouldDispatchAfterCommit defers the actual broadcast until the
        // enclosing transaction commits — safe to dispatch unconditionally
        // here even though transition() is often called from inside
        // another action's own transaction (see OrderStatusChanged's
        // docblock).
        OrderStatusChanged::dispatch($order, $from, $to);

        return $order;
    }

    /**
     * Records the initial `pending` row in the history table — called
     * once, right after an order is created, so the timeline always has
     * a starting point (`from_status` null).
     */
    public function recordInitial(Order $order, ?User $actor = null): void
    {
        $history = new OrderStatusHistory([
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => $order->status,
            'changed_by' => $actor?->id,
            'notes' => null,
        ]);
        $history->save();
    }
}
