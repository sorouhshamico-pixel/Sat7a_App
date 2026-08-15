<?php

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency ledger — see docs/PAYMENT_ARCHITECTURE.md §Webhooks. Written
 * only by App\Domain\Payments\Actions\ProcessPaymentWebhookAction.
 */
#[Fillable(['gateway', 'event_id', 'event_type', 'payload', 'processed_at'])]
class PaymentWebhookEvent extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
