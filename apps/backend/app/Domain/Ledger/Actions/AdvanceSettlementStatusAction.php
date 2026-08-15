<?php

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\Enums\LedgerEntryDirection;
use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Domain\Ledger\Enums\SettlementStatus;
use App\Domain\Ledger\Exceptions\SettlementException;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Models\SettlementBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single place a settlement batch's status ever changes — mirrors
 * App\Domain\Orders\Services\OrderStateMachine and
 * App\Domain\Payments\Services\PaymentStateMachine. Validates against
 * SettlementStatus::allowedTransitions(), records the right side effect
 * per target status, and — critically — is the only place a `settlement`
 * ledger entry is ever created (on reaching `paid`) or a batch's claimed
 * entries are released back to unclaimed (on reaching `failed`/
 * `cancelled`, see SettlementStatus::releasesClaimedEntries()).
 */
class AdvanceSettlementStatusAction
{
    /**
     * @throws SettlementException
     */
    public function handle(
        SettlementBatch $batch,
        SettlementStatus $to,
        User $actor,
        ?string $reference = null,
        ?string $failureReason = null,
    ): SettlementBatch {
        $from = $batch->status;

        if (! $from->canTransitionTo($to)) {
            throw SettlementException::invalidTransition();
        }

        if ($to === SettlementStatus::Paid) {
            $bankAccount = $batch->provider->bankAccount;

            if ($bankAccount === null) {
                throw SettlementException::bankAccountNotFound();
            }

            if (! $bankAccount->verified) {
                throw SettlementException::bankAccountNotVerified();
            }
        }

        return DB::transaction(function () use ($batch, $to, $actor, $reference, $failureReason): SettlementBatch {
            $batch->status = $to;

            match ($to) {
                SettlementStatus::Approved => $batch->approved_by = $actor->id,
                SettlementStatus::Paid => $batch->paid_at = now(),
                default => null,
            };

            if ($reference !== null) {
                $batch->reference = $reference;
            }
            if ($failureReason !== null) {
                $batch->failure_reason = $failureReason;
            }

            $batch->save();

            if ($to === SettlementStatus::Paid) {
                $entry = new LedgerEntry([
                    'provider_id' => $batch->provider_id,
                    'type' => LedgerEntryType::Settlement,
                    'direction' => LedgerEntryDirection::Debit,
                    'amount' => $batch->net,
                ]);
                $entry->settlement_batch_id = $batch->id;
                $entry->save();
            }

            if ($to->releasesClaimedEntries()) {
                LedgerEntry::query()->where('settlement_batch_id', $batch->id)->update(['settlement_batch_id' => null]);
            }

            return $batch;
        });
    }
}
