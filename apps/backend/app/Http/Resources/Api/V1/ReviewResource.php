<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Reviews\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'order_id' => $this->whenLoaded('order', fn () => $this->order?->public_id),
            'provider_id' => $this->whenLoaded('provider', fn () => $this->provider?->public_id),
            'driver_id' => $this->whenLoaded('driver', fn () => $this->driver?->public_id),
            'rating' => $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at,
        ];
    }
}
