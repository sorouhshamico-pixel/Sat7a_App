<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Fleet\Enums\TowTruckStatus;
use App\Domain\Orders\Enums\OrderCancelledBy;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderCancelled;
use App\Domain\Orders\Exceptions\OrderException;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderStateMachine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

        return DB::transaction(function () use ($order, $targetStatus, $cancelledBy, $actor, $reason): Order {
            $order = $this->stateMachine->transition($order, $targetStatus, $actor, $reason);

            $order->cancelled_by = $cancelledBy;
            $order->cancellation_reason = $reason;
            $order->cancelled_at = now();
            $order->save();

            // A tow truck reserved for this order (accepted but the trip
            // never got underway) must never be stuck unable to take new
            // work just because the order it was reserved for was
            // cancelled — see App\Domain\Orders\Actions\AdvanceTripStatusAction.
            $towTruck = $order->assignedTowTruck;
            if ($towTruck !== null && $towTruck->status->canTransitionTo(TowTruckStatus::Available)) {
                $towTruck->status = TowTruckStatus::Available;
                $towTruck->save();
            }

            OrderCancelled::dispatch($order, $cancelledBy, $reason);

            return $order;
        });
    }
}
