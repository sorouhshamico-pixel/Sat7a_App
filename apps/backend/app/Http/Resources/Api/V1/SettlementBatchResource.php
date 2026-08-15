<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Ledger\Models\SettlementBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SettlementBatch
 */
class SettlementBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'provider_id' => $this->whenLoaded('provider', fn () => $this->provider?->public_id),
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'gross' => $this->gross,
            'commission' => $this->commission,
            'deductions' => $this->deductions,
            'net' => $this->net,
            'status' => $this->status->value,
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->public_id),
            'paid_at' => $this->paid_at,
            'reference' => $this->reference,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
