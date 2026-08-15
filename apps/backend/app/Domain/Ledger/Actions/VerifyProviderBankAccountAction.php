<?php

namespace App\Domain\Ledger\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Ledger\Models\ProviderBankAccount;
use App\Models\User;

/**
 * Compliance/finance staff sign-off before a bank account can receive a
 * settlement payout — see AdvanceSettlementStatusAction, which rejects
 * any transition to `paid` while `verified` is false.
 */
class VerifyProviderBankAccountAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ProviderBankAccount $bankAccount, User $actor): ProviderBankAccount
    {
        $bankAccount->verified = true;
        $bankAccount->verified_by = $actor->id;
        $bankAccount->verified_at = now();
        $bankAccount->save();

        $this->auditLogger->log(
            actor: $actor,
            action: 'settlements.bank_account_verified',
            entityType: 'provider_bank_account',
            entityId: $bankAccount->public_id,
            newValues: ['iban' => $bankAccount->maskedIban()],
        );

        return $bankAccount->fresh();
    }
}
