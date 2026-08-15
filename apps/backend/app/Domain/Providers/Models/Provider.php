<?php

namespace App\Domain\Providers\Models;

use App\Domain\Compliance\Models\Document;
use App\Domain\Drivers\Models\Driver;
use App\Domain\Fleet\Models\TowTruck;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Models\ProviderBankAccount;
use App\Domain\Ledger\Models\SettlementBatch;
use App\Domain\Providers\Enums\ProviderStatus;
use App\Models\User;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property ProviderStatus $status
 * @property Carbon|null $approved_at
 */
#[Fillable(['business_name', 'commercial_registration_number', 'tax_number', 'contact_phone', 'contact_email', 'status', 'rejection_reason', 'suspension_reason', 'approved_at', 'approved_by'])]
class Provider extends Model
{
    use HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProviderStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * @return HasMany<Driver, $this>
     */
    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    /**
     * @return HasMany<TowTruck, $this>
     */
    public function towTrucks(): HasMany
    {
        return $this->hasMany(TowTruck::class);
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * @return HasOne<ProviderBankAccount, $this>
     */
    public function bankAccount(): HasOne
    {
        return $this->hasOne(ProviderBankAccount::class);
    }

    /**
     * @return HasMany<SettlementBatch, $this>
     */
    public function settlementBatches(): HasMany
    {
        return $this->hasMany(SettlementBatch::class);
    }
}
