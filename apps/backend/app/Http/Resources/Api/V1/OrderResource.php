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
