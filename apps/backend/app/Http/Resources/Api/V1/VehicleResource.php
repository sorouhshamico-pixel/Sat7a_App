<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Customers\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'type' => $this->type,
            'color' => $this->color,
            'plate_number' => $this->plate_number,
            'notes' => $this->notes,
            'image_url' => $this->image_path !== null ? Storage::disk('public')->url($this->image_path) : null,
            'created_at' => $this->created_at,
        ];
    }
}
