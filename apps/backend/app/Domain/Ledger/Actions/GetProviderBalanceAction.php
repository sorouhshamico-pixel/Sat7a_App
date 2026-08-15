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
 * - `available_balance`: earned outside that window, not yet settled —
 *   this is what Phase 14 (Settlements) will actually pay out.
 * - `settled_balance`: sum of `settlement` entries — always `0` today,
 *   since nothing creates one until Phase 14 exists, but the formula is
 *   ready for when it does.
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
                $settled += $signed;
            } elseif ($entry->created_at->greaterThanOrEqualTo($cutoff)) {
                $pending += $signed;
            }
        }

        return [
            'pending_balance' => $pending,
            'available_balance' => $totalPayable - $pending - $settled,
            'settled_balance' => $settled,
            'total_payable' => $totalPayable,
        ];
    }
}
