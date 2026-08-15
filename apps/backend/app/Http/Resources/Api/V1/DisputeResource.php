<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Disputes\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dispute
 */
class DisputeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'order_id' => $this->whenLoaded('order', fn () => $this->order?->public_id),
            'reason' => $this->reason->value,
            'description' => $this->description,
            'status' => $this->status->value,
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo?->public_id),
            'resolution_notes' => $this->resolution_notes,
            'resolved_by' => $this->whenLoaded('resolvedBy', fn () => $this->resolvedBy?->public_id),
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
        ];
    }
}
