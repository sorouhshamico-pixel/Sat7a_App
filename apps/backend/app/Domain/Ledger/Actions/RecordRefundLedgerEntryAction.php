<?php

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\Enums\LedgerEntryDirection;
use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payments\Models\Refund;
use Illuminate\Support\Facades\Log;

/**
 * Runs once per successful refund (see
 * App\Domain\Ledger\Listeners\RecordCommissionListener, subscribed to
 * App\Domain\Payments\Events\RefundProcessed). Reverses the original
 * `provider_payable` entry's balance impact *proportionally* — a partial
 * refund only reverses its share, not the whole thing. This also handles
 * the cash case correctly with no special-casing: cash's original
 * provider-payable entry is a debit (see
 * RecordPaymentLedgerEntriesAction), so reversing part of it naturally
 * produces a credit — the provider owes the platform less, since part of
 * the underlying sale was voided.
 */
class RecordRefundLedgerEntryAction
{
    public function handle(Refund $refund): void
    {
        $payment = $refund->payment;

        $originalEntry = LedgerEntry::query()
            ->where('payment_id', $payment->id)
            ->where('type', LedgerEntryType::ProviderPayable)
            ->first();

        if ($originalEntry === null) {
            Log::warning('ledger.refund_without_original_payable_entry', [
                'refund_id' => $refund->public_id,
                'payment_id' => $payment->public_id,
            ]);

            return;
        }

        $refundRatio = $payment->amount > 0 ? $refund->amount / $payment->amount : 0;
        $reversalAmount = (int) round($originalEntry->signedAmount() * $refundRatio) * -1;

        if ($reversalAmount === 0) {
            return;
        }

        $entry = new LedgerEntry([
            'order_id' => $originalEntry->order_id,
            'payment_id' => $payment->id,
            'provider_id' => $originalEntry->provider_id,
            'type' => LedgerEntryType::Refund,
            'direction' => $reversalAmount >= 0 ? LedgerEntryDirection::Credit : LedgerEntryDirection::Debit,
            'amount' => abs($reversalAmount),
        ]);
        $entry->save();
    }
}
