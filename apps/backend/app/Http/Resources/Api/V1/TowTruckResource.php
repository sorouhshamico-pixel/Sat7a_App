<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Fleet\Models\TowTruck;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TowTruck
 */
class TowTruckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'year' => $this->year,
            'plate_number' => $this->plate_number,
            'capacity' => $this->capacity,
            'service_capabilities' => $this->service_capabilities,
            'status' => $this->status->value,
            'driver' => $this->whenLoaded('driver', fn () => $this->driver === null ? null : new DriverResource($this->driver)),
            'current_latitude' => $this->current_latitude,
            'current_longitude' => $this->current_longitude,
            'last_location_at' => $this->last_location_at,
            'created_at' => $this->created_at,
        ];
    }
}
