<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Customers\Models\SavedLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedLocation
 */
class SavedLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'label' => $this->label->value,
            'custom_label' => $this->custom_label,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'formatted_address' => $this->formatted_address,
            'place_id' => $this->place_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
