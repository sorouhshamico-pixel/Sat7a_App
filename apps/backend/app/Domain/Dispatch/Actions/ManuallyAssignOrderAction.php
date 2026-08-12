<?php

namespace App\Domain\Dispatch\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Dispatch\Exceptions\DispatchException;
use App\Domain\Drivers\Enums\DriverStatus;
use App\Domain\Fleet\Enums\TowTruckStatus;
use App\Domain\Fleet\Models\TowTruck;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderStateMachine;
use App\Domain\Providers\Enums\ProviderStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Operations staff manually assigning a specific tow truck to an order —
 * the fallback once automated dispatch waves are exhausted (see
 * docs/DISPATCH_ENGINE.md §Manual fallback). Still enforces the normal
 * eligibility checks (truck available and capable, provider approved,
 * driver active); bypassing eligibility entirely is a distinct,
 * not-yet-implemented "override" action reserved for a proven need.
 * Always audited.
 */
class ManuallyAssignOrderAction
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws DispatchException
     */
    public function handle(Order $order, TowTruck $towTruck, User $actor, ?string $reason = null): Order
    {
        if (! in_array($order->status, [OrderStatus::Pending, OrderStatus::SearchingProvider], true)) {
            throw DispatchException::orderNotDispatchable();
        }

        $towTruck->loadMissing(['driver', 'provider']);

        $eligible = $towTruck->status === TowTruckStatus::Available
            && $towTruck->driver !== null
            && $towTruck->driver->status === DriverStatus::Active
            && $towTruck->provider->status === ProviderStatus::Approved
            && in_array($order->service_type, $towTruck->service_capabilities, true);

        if (! $eligible) {
            throw DispatchException::towTruckNotEligible();
        }

        return DB::transaction(function () use ($order, $towTruck, $actor, $reason): Order {
            $order->assigned_provider_id = $towTruck->provider_id;
            $order->assigned_driver_id = $towTruck->driver_id;
            $order->assigned_tow_truck_id = $towTruck->id;
            $order->accepted_at = now();
            $order->manual_dispatch_required = false;
            $order->save();

            if ($order->status === OrderStatus::Pending) {
                $order = $this->stateMachine->transition($order, OrderStatus::SearchingProvider, $actor);
            }

            $order = $this->stateMachine->transition($order, OrderStatus::ProviderAssigned, $actor, $reason);

            $towTruck->status = TowTruckStatus::Reserved;
            $towTruck->save();

            $this->auditLogger->log(
                actor: $actor,
                action: 'orders.manually_assigned',
                entityType: 'order',
                entityId: $order->public_id,
                newValues: ['tow_truck_id' => $towTruck->public_id],
                reason: $reason,
            );

            return $order;
        });
    }
}
