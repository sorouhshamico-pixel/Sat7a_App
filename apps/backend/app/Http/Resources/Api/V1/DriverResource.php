<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Drivers\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Driver
 */
class DriverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->whenLoaded('user', fn () => $this->user->name),
            'phone' => $this->whenLoaded('user', fn () => $this->user->phone),
            'nationality' => $this->nationality,
            'license_number' => $this->license_number,
            'license_expires_at' => $this->license_expires_at,
            'status' => $this->status->value,
            'is_available' => $this->is_available,
            'rating' => $this->rating,
            'tow_truck_id' => $this->whenLoaded('towTruck', fn () => $this->towTruck?->public_id),
            'created_at' => $this->created_at,
        ];
    }
}
