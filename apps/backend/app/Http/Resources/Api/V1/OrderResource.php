<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'status' => $this->status->value,
            'service_type' => $this->service_type,
            'vehicle' => $this->whenLoaded('vehicle', fn () => new VehicleResource($this->vehicle)),
            'pickup' => [
                'latitude' => (float) $this->pickup_latitude,
                'longitude' => (float) $this->pickup_longitude,
                'formatted_address' => $this->pickup_formatted_address,
            ],
            'dropoff' => [
                'latitude' => (float) $this->dropoff_latitude,
                'longitude' => (float) $this->dropoff_longitude,
                'formatted_address' => $this->dropoff_formatted_address,
            ],
            'notes' => $this->notes,
            'pricing_snapshot' => $this->pricing_snapshot,
            'quoted_price' => $this->quoted_price,
            'final_price' => $this->final_price,
            'payment_method' => $this->payment_method->value,
            'current_dispatch_wave' => $this->current_dispatch_wave,
            'manual_dispatch_required' => $this->manual_dispatch_required,
            'assigned_provider' => $this->whenLoaded('assignedProvider', fn () => $this->assignedProvider === null ? null : [
                'id' => $this->assignedProvider->public_id,
                'business_name' => $this->assignedProvider->business_name,
            ]),
            'assigned_driver' => $this->whenLoaded('assignedDriver', fn () => $this->assignedDriver === null ? null : new DriverResource($this->assignedDriver)),
            'assigned_tow_truck' => $this->whenLoaded('assignedTowTruck', fn () => $this->assignedTowTruck === null ? null : new TowTruckResource($this->assignedTowTruck)),
            'cancelled_by' => $this->cancelled_by?->value,
            'cancellation_reason' => $this->cancellation_reason,
            'cancellation_fee' => $this->cancellation_fee,
            'accepted_at' => $this->accepted_at,
            'arrived_at' => $this->arrived_at,
            'trip_started_at' => $this->trip_started_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
        ];
    }
}
