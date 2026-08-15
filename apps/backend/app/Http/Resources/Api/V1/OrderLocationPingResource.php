<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Tracking\Models\OrderLocationPing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderLocationPing
 */
class OrderLocationPingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'heading' => $this->heading,
            'speed_kmh' => $this->speed_kmh,
            'recorded_at' => $this->recorded_at,
        ];
    }
}
