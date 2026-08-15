<?php

namespace App\Domain\Ledger\Models;

use App\Domain\Ledger\Enums\LedgerEntryDirection;
use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Providers\Models\Provider;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The financial facts (`type`/`direction`/`amount`/...) are immutable,
 * append-only — see docs/DATABASE_SCHEMA.md §Immutability. Written only
 * by App\Domain\Ledger\Actions\RecordPaymentLedgerEntriesAction and
 * RecordRefundLedgerEntryAction, never updated or deleted. The one
 * exception is `settlement_batch_id`, a bookkeeping marker (not a
 * financial fact) set when a settlement batch claims the entry and
 * cleared back to `null` if that batch later fails or is cancelled — see
 * App\Domain\Ledger\Actions\AdvanceSettlementStatusAction and
 * docs/SETTLEMENT_ARCHITECTURE.md.
 *
 * @property LedgerEntryType $type
 * @property LedgerEntryDirection $direction
 * @property Carbon $created_at
 */
#[Fillable(['order_id', 'payment_id', 'provider_id', 'type', 'direction', 'amount', 'currency', 'description'])]
class LedgerEntry extends Model
{
    use HasUlid;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'direction' => LedgerEntryDirection::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * Positive for a credit (increases the provider's balance), negative
     * for a debit (decreases it) — the single place that sign convention
     * is applied, so balance math never has to re-derive it.
     */
    public function signedAmount(): int
    {
        return $this->direction === LedgerEntryDirection::Credit ? $this->amount : -$this->amount;
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return BelongsTo<SettlementBatch, $this>
     */
    public function settlementBatch(): BelongsTo
    {
        return $this->belongsTo(SettlementBatch::class);
    }
}
