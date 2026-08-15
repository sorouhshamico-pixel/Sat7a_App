<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Payments\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Refund
 */
class RefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at,
        ];
    }
}
