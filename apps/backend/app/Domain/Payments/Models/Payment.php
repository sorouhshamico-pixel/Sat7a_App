<?php

namespace App\Domain\Payments\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\RefundStatus;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property PaymentStatus $status
 * @property PaymentMethod $method
 * @property Carbon|null $captured_at
 */
#[Fillable(['gateway', 'gateway_payment_id', 'method', 'amount', 'currency', 'card_brand', 'card_last_four', 'failure_reason', 'idempotency_key'])]
class Payment extends Model
{
    use HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'method' => PaymentMethod::class,
            'authorized_at' => 'datetime',
            'captured_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Sum of every successfully processed refund — never trust a single
     * `refunds` row's status without this, since a `pending`/`failed`
     * attempt must never count against the refundable balance.
     */
    public function refundedAmount(): int
    {
        return (int) $this->refunds()
            ->where('status', RefundStatus::Succeeded)
            ->sum('amount');
    }
}
