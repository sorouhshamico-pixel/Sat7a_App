<?php

namespace App\Domain\Ledger\Models;

use App\Domain\Ledger\Enums\SettlementStatus;
use App\Domain\Providers\Models\Provider;
use App\Models\User;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property SettlementStatus $status
 */
#[Fillable(['provider_id', 'period_start', 'period_end', 'gross', 'commission', 'deductions', 'net', 'reference', 'failure_reason'])]
class SettlementBatch extends Model
{
    use HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SettlementStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
