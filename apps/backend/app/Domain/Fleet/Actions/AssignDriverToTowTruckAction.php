<?php

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Exceptions\FleetException;
use App\Domain\Fleet\Models\TowTruck;
use App\Support\Enums\ErrorCode;

class AssignDriverToTowTruckAction
{
    public function handle(TowTruck $towTruck, ?int $driverId): TowTruck
    {
        if ($driverId !== null) {
            $driver = $towTruck->provider->drivers()->find($driverId);

            if ($driver === null) {
                throw new FleetException(ErrorCode::NotFound, 'Driver not found for this provider.', 404);
            }

            if ($driver->towTruck()->whereKeyNot($towTruck->id)->exists()) {
                throw new FleetException(ErrorCode::ValidationFailed, 'This driver is already assigned to another tow truck.', 422);
            }
        }

        $towTruck->update(['driver_id' => $driverId]);

        return $towTruck->fresh();
    }
}
