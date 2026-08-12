<?php

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Enums\TowTruckStatus;
use App\Domain\Fleet\Models\TowTruck;
use App\Domain\Providers\Models\Provider;

class AddTowTruckAction
{
    /**
     * @param  array{manufacturer: string, model: string, year: int, plate_number: string, capacity: ?string, service_capabilities: list<string>}  $data
     */
    public function handle(Provider $provider, array $data): TowTruck
    {
        $towTruck = new TowTruck([
            'manufacturer' => $data['manufacturer'],
            'model' => $data['model'],
            'year' => $data['year'],
            'plate_number' => $data['plate_number'],
            'capacity' => $data['capacity'] ?? null,
            'service_capabilities' => $data['service_capabilities'],
            'status' => TowTruckStatus::Offline->value,
        ]);
        $towTruck->provider_id = $provider->id;
        $towTruck->save();

        return $towTruck;
    }
}
