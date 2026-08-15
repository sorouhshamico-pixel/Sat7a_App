<?php

namespace App\Domain\Ledger\Models;

use App\Domain\Providers\Models\Provider;
use App\Models\User;
use App\Support\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One current bank account per provider — see
 * docs/SETTLEMENT_ARCHITECTURE.md §Bank account security. `iban` is
 * encrypted at rest (see docs/SECURITY.md §Encryption); masked in
 * general admin UI, full value requires the
 * `settlements.view_bank_details` permission (see
 * App\Http\Resources\Api\V1\ProviderBankAccountResource).
 */
#[Fillable(['account_holder_name', 'iban', 'bank_name'])]
class ProviderBankAccount extends Model
{
    use HasUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iban' => 'encrypted',
            'verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function maskedIban(): string
    {
        $iban = $this->iban;
        $tail = substr($iban, -4);

        return str_repeat('*', max(0, strlen($iban) - 4)).$tail;
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
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
