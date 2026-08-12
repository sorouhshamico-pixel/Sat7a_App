<?php

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Enums\TowTruckStatus;
use App\Domain\Fleet\Exceptions\FleetException;
use App\Domain\Fleet\Models\TowTruck;
use App\Support\Enums\ErrorCode;

/**
 * Only the offline/available/maintenance/unavailable subgraph is reachable
 * through this action — see App\Domain\Fleet\Enums\TowTruckStatus. The
 * dispatch-driven states are set by the dispatch/trip system from Phase 9
 * onward, reusing this same action (and therefore the same transition
 * matrix) rather than writing the status column directly.
 */
class UpdateTowTruckStatusAction
{
    private const PROVIDER_SETTABLE_STATUSES = [
        TowTruckStatus::Offline,
        TowTruckStatus::Available,
        TowTruckStatus::Maintenance,
        TowTruckStatus::Unavailable,
    ];

    public function handle(TowTruck $towTruck, TowTruckStatus $target): TowTruck
    {
        if (! in_array($target, self::PROVIDER_SETTABLE_STATUSES, true)) {
            throw new FleetException(ErrorCode::ValidationFailed, 'This status cannot be set manually.', 422);
        }

        if (! $towTruck->status->canTransitionTo($target)) {
            throw new FleetException(
                ErrorCode::ValidationFailed,
                "Cannot transition a tow truck from {$towTruck->status->value} to {$target->value}.",
                422,
            );
        }

        $towTruck->update(['status' => $target->value]);

        return $towTruck->fresh();
    }
}
