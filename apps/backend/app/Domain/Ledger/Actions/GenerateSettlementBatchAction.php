<?php

namespace App\Domain\Ledger\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Domain\Ledger\Enums\SettlementStatus;
use App\Domain\Ledger\Exceptions\SettlementException;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Models\SettlementBatch;
use App\Domain\Providers\Models\Provider;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Claims" every currently-unclaimed, past-the-hold-window balance-
 * affecting ledger entry for a provider dated on or before `periodEnd`
 * (no lower bound — an old entry missed by a previous batch is always
 * swept into the next one rather than silently lost, see
 * docs/SETTLEMENT_ARCHITECTURE.md) into a new `draft` batch. `gross`/
 * `commission`/`deductions` are informational sums derived from the same
 * underlying payments, for display only — `net` (which can be negative,
 * see docs/PAYMENT_ARCHITECTURE.md's cash-payment case) is the actual
 * balance-affecting total and the only figure that matters once this
 * batch is eventually marked `paid`.
 */
class GenerateSettlementBatchAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @throws SettlementException
     */
    public function handle(Provider $provider, Carbon $periodStart, Carbon $periodEnd, User $actor): SettlementBatch
    {
        $holdCutoff = now()->subHours((int) config('ledger.pending_hold_hours', 24));

        $eligible = LedgerEntry::query()
            ->where('provider_id', $provider->id)
            ->whereNull('settlement_batch_id')
            ->whereIn('type', array_map(
                fn (LedgerEntryType $type) => $type->value,
                array_filter(LedgerEntryType::cases(), fn (LedgerEntryType $type) => $type->affectsProviderBalance()),
            ))
            ->where('created_at', '<=', $periodEnd)
            ->where('created_at', '<=', $holdCutoff)
            ->get();

        $net = (int) $eligible->sum(fn (LedgerEntry $entry) => $entry->signedAmount());

        if ($eligible->isEmpty() || $net <= 0) {
            throw SettlementException::noEligibleEarnings();
        }

        $paymentIds = $eligible->pluck('payment_id')->filter()->unique()->values();

        $gross = (int) LedgerEntry::query()->whereIn('payment_id', $paymentIds)->where('type', LedgerEntryType::CustomerPayment)->sum('amount');
        $commission = (int) LedgerEntry::query()->whereIn('payment_id', $paymentIds)->where('type', LedgerEntryType::PlatformCommission)->sum('amount');
        $deductions = (int) LedgerEntry::query()->whereIn('payment_id', $paymentIds)->where('type', LedgerEntryType::GatewayFee)->sum('amount');

        return DB::transaction(function () use ($provider, $periodStart, $periodEnd, $gross, $commission, $deductions, $net, $eligible, $actor): SettlementBatch {
            $batch = new SettlementBatch([
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'gross' => $gross,
                'commission' => $commission,
                'deductions' => $deductions,
                'net' => $net,
            ]);
            $batch->provider_id = $provider->id;
            $batch->status = SettlementStatus::Draft;
            $batch->save();

            LedgerEntry::query()->whereIn('id', $eligible->pluck('id'))->update(['settlement_batch_id' => $batch->id]);

            $this->auditLogger->log(
                actor: $actor,
                action: 'settlements.batch_generated',
                entityType: 'settlement_batch',
                entityId: $batch->public_id,
                newValues: ['provider_id' => $provider->public_id, 'net' => $net],
            );

            return $batch;
        });
    }
}
