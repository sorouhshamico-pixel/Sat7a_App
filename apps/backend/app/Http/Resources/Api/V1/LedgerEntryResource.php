<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Ledger\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LedgerEntry
 */
class LedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'order_id' => $this->whenLoaded('order', fn () => $this->order?->public_id),
            'type' => $this->type->value,
            'direction' => $this->direction->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
            'created_at' => $this->created_at,
        ];
    }
}
