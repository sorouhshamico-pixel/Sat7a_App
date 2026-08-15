<?php

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Providers\Models\Provider;

/**
 * Provider balance is never just `sum(completed orders)` — it's derived
 * from the ledger (see docs/SETTLEMENT_ARCHITECTURE.md). Three buckets:
 *
 * - `pending_balance`: earned within the last `ledger.pending_hold_hours`
 *   — a short fraud/dispute-protection window before money is
 *   considered clear.
 * - `available_balance`: currently owed and outside the pending window —
 *   this is what a new settlement batch (Phase 14) can actually pay out.
 * - `settled_balance`: lifetime total already paid out via `settlement`
 *   entries (see App\Domain\Ledger\Actions\AdvanceSettlementStatusAction,
 *   which records one as a debit for `net` when a batch reaches `paid`) —
 *   purely informational, not subtracted a second time from
 *   `available_balance` since the debit already reduced `total_payable`.
 * - `total_payable`: the CURRENT amount owed to the provider right now —
 *   every entry ever recorded, netted together, so a `settlement` debit
 *   here already cancels out the batch of entries it paid off.
 *
 * A negative balance is valid and expected — see the cash-payment case
 * in RecordPaymentLedgerEntriesAction, where a provider who already
 * collected cash directly ends up owing the platform its commission.
 */
class GetProviderBalanceAction
{
    /**
     * @return array{pending_balance: int, available_balance: int, settled_balance: int, total_payable: int}
     */
    public function handle(Provider $provider): array
    {
        $cutoff = now()->subHours((int) config('ledger.pending_hold_hours', 24));

        $entries = LedgerEntry::query()
            ->where('provider_id', $provider->id)
            ->whereIn('type', array_map(
                fn (LedgerEntryType $type) => $type->value,
                array_filter(LedgerEntryType::cases(), fn (LedgerEntryType $type) => $type->affectsProviderBalance()),
            ))
            ->get();

        $settled = 0;
        $pending = 0;
        $totalPayable = 0;

        foreach ($entries as $entry) {
            $signed = $entry->signedAmount();
            $totalPayable += $signed;

            if ($entry->type === LedgerEntryType::Settlement) {
                // Always a debit (see AdvanceSettlementStatusAction) — flip
                // the sign so this reads as a positive lifetime "paid so
                // far" figure rather than the negative balance-impact.
                $settled += -$signed;
            } elseif ($entry->created_at->greaterThanOrEqualTo($cutoff)) {
                $pending += $signed;
            }
        }

        return [
            'pending_balance' => $pending,
            'available_balance' => $totalPayable - $pending,
            'settled_balance' => $settled,
            'total_payable' => $totalPayable,
        ];
    }
}
