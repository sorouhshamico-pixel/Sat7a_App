<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Dispatch\Models\DispatchOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DispatchOffer
 */
class DispatchOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'status' => $this->status->value,
            'wave' => $this->wave,
            'distance_meters' => $this->distance_meters,
            'expires_at' => $this->expires_at,
            'responded_at' => $this->responded_at,
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->public_id,
                'service_type' => $this->order->service_type,
                'pickup_formatted_address' => $this->order->pickup_formatted_address,
                'dropoff_formatted_address' => $this->order->dropoff_formatted_address,
                'quoted_price' => $this->order->quoted_price,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
