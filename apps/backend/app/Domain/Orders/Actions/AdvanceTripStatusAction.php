<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Fleet\Enums\TowTruckStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Exceptions\OrderException;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderStateMachine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The driver's own trip-progress reporting — see
 * docs/LIVE_LOCATION_TRACKING.md. Deliberately only exposes the forward,
 * single-step chain a driver is allowed to drive through this endpoint;
 * cancellation/dispute/refund states are never reachable here (validated
 * both by the explicit whitelist and, redundantly, by
 * OrderStateMachine::transition()'s own adjacency check, so a driver can
 * never skip a step even if they pass a technically-valid future status).
 * Mirrors the assigned tow truck's status forward in step (see
 * App\Domain\Fleet\Enums\TowTruckStatus) — completing a trip frees the
 * truck back to `available` so it can be dispatched again, closing a gap
 * left open since Phase 9 (a truck accepted an offer and had nothing that
 * ever returned it to service).
 */
class AdvanceTripStatusAction
{
    /**
     * @return list<OrderStatus>
     */
    public const DRIVER_ADVANCEABLE_STATUSES = [
        OrderStatus::ProviderEnRoute,
        OrderStatus::ProviderArrived,
        OrderStatus::VehicleLoading,
        OrderStatus::TripStarted,
        OrderStatus::InTransit,
        OrderStatus::VehicleDelivered,
        OrderStatus::Completed,
    ];

    public function __construct(private readonly OrderStateMachine $stateMachine) {}

    /**
     * @throws OrderException
     */
    public function handle(Order $order, OrderStatus $to, User $actor): Order
    {
        if (! in_array($to, self::DRIVER_ADVANCEABLE_STATUSES, true)) {
            throw OrderException::invalidTransition($order->status, $to);
        }

        return DB::transaction(function () use ($order, $to, $actor): Order {
            $order = $this->stateMachine->transition($order, $to, $actor);

            match ($to) {
                OrderStatus::ProviderArrived => $order->arrived_at = now(),
                OrderStatus::TripStarted => $order->trip_started_at = now(),
                OrderStatus::Completed => $order->completed_at = now(),
                default => null,
            };

            if ($order->isDirty()) {
                $order->save();
            }

            $this->advanceTowTruck($order, $to);

            return $order;
        });
    }

    private function advanceTowTruck(Order $order, OrderStatus $to): void
    {
        $towTruck = $order->assignedTowTruck;

        if ($towTruck === null) {
            return;
        }

        $targetStatus = match ($to) {
            OrderStatus::ProviderEnRoute => TowTruckStatus::EnRoute,
            OrderStatus::ProviderArrived => TowTruckStatus::Arrived,
            OrderStatus::VehicleLoading => TowTruckStatus::Loading,
            OrderStatus::TripStarted => TowTruckStatus::OnTrip,
            OrderStatus::Completed => TowTruckStatus::Available,
            default => null,
        };

        if ($targetStatus !== null && $towTruck->status->canTransitionTo($targetStatus)) {
            $towTruck->status = $targetStatus;
            $towTruck->save();
        }
    }
}
