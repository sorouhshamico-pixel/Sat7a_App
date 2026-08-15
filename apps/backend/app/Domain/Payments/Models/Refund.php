<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\RefundStatus;
use App\Models\User;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property RefundStatus $status
 */
#[Fillable(['amount', 'reason', 'status', 'gateway_refund_id', 'failure_reason'])]
class Refund extends Model
{
    use HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
