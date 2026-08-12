<?php

namespace App\Domain\Drivers\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Drivers\Enums\DriverStatus;
use App\Domain\Drivers\Models\Driver;
use App\Models\User;

class SuspendDriverAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Driver $driver, User $actor, string $reason): Driver
    {
        $previousStatus = $driver->status;

        $driver->update(['status' => DriverStatus::Suspended->value, 'is_available' => false]);

        $this->auditLogger->log(
            actor: $actor,
            action: 'driver.suspended',
            entityType: 'driver',
            entityId: $driver->public_id,
            oldValues: ['status' => $previousStatus->value],
            newValues: ['status' => DriverStatus::Suspended->value],
            reason: $reason,
        );

        return $driver->fresh();
    }
}
