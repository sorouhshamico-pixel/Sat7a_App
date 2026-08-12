<?php

namespace App\Domain\Fleet\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Fleet\Enums\TowTruckStatus;
use App\Domain\Fleet\Models\TowTruck;
use App\Models\User;

/**
 * Unlike the provider-facing status update, suspension can be entered from
 * any state — it's a compliance override, not a normal operational
 * transition (see App\Domain\Fleet\Enums\TowTruckStatus).
 */
class SuspendTowTruckAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(TowTruck $towTruck, User $actor, string $reason): TowTruck
    {
        $previousStatus = $towTruck->status;

        $towTruck->update(['status' => TowTruckStatus::Suspended->value]);

        $this->auditLogger->log(
            actor: $actor,
            action: 'tow_truck.suspended',
            entityType: 'tow_truck',
            entityId: $towTruck->public_id,
            oldValues: ['status' => $previousStatus->value],
            newValues: ['status' => TowTruckStatus::Suspended->value],
            reason: $reason,
        );

        return $towTruck->fresh();
    }
}
