<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Payments\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'order_id' => $this->whenLoaded('order', fn () => $this->order->public_id),
            'status' => $this->status->value,
            'method' => $this->method->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'card_brand' => $this->card_brand,
            'card_last_four' => $this->card_last_four,
            'failure_reason' => $this->failure_reason,
            'refunded_amount' => $this->refundedAmount(),
            'authorized_at' => $this->authorized_at,
            'captured_at' => $this->captured_at,
            'failed_at' => $this->failed_at,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
        ];
    }
}
