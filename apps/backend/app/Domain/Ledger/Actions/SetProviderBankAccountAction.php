<?php

namespace App\Domain\Ledger\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Ledger\Models\ProviderBankAccount;
use App\Domain\Providers\Models\Provider;
use App\Models\User;

/**
 * Provider self-service create/update of their single payout bank
 * account (see docs/SETTLEMENT_ARCHITECTURE.md §Bank account security).
 * Any change — including the first-ever save — resets `verified` to
 * `false`: a provider who edits their IBAN after being verified must be
 * re-verified before another settlement can be marked `paid` against it
 * (enforced by App\Domain\Ledger\Actions\AdvanceSettlementStatusAction).
 * The audit trail intentionally never stores the raw IBAN, even
 * encrypted — only the masked form, matching the same "never log the
 * sensitive value itself" rule applied to documents in Phase 6.
 */
class SetProviderBankAccountAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{account_holder_name: string, iban: string, bank_name: string}  $data
     */
    public function handle(Provider $provider, array $data, User $actor): ProviderBankAccount
    {
        $bankAccount = $provider->bankAccount;
        $previousMasked = $bankAccount?->maskedIban();

        if ($bankAccount === null) {
            $bankAccount = new ProviderBankAccount($data);
            $bankAccount->provider_id = $provider->id;
        } else {
            $bankAccount->fill($data);
        }

        $bankAccount->verified = false;
        $bankAccount->verified_by = null;
        $bankAccount->verified_at = null;
        $bankAccount->save();

        $this->auditLogger->log(
            actor: $actor,
            action: $previousMasked === null ? 'settlements.bank_account_created' : 'settlements.bank_account_updated',
            entityType: 'provider_bank_account',
            entityId: $bankAccount->public_id,
            oldValues: $previousMasked === null ? null : ['iban' => $previousMasked],
            newValues: ['iban' => $bankAccount->maskedIban()],
        );

        return $bankAccount->fresh();
    }
}
