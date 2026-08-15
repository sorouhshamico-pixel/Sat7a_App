<?php

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\Enums\LedgerEntryDirection;
use App\Domain\Ledger\Enums\LedgerEntryType;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Runs once per captured payment (see
 * App\Domain\Ledger\Listeners\RecordCommissionListener, subscribed to
 * App\Domain\Payments\Events\PaymentCaptured) — see
 * docs/SETTLEMENT_ARCHITECTURE.md §Commission for the formula.
 *
 * Card payments: the platform receives the gross amount from the gateway
 * and owes the provider `gross - commission - gateway_fee - tax` — a
 * credit (the platform owes the provider money).
 *
 * Cash payments: the provider already collected the full gross amount in
 * person — the platform received nothing, so the provider instead owes
 * the platform `commission + tax` (there's no gateway fee, no gateway was
 * involved) — a debit (the provider owes the platform money), which
 * reduces future payouts once Phase 14 (Settlements) exists.
 */
class RecordPaymentLedgerEntriesAction
{
    public function handle(Payment $payment): void
    {
        $order = $payment->order;
        $providerId = $order->assigned_provider_id;

        if ($providerId === null) {
            // Shouldn't happen — a payment is only creatable once an
            // order has reached vehicle_delivered/completed, which
            // requires a provider assignment (see
            // docs/PAYMENT_ARCHITECTURE.md). Defensive only.
            Log::warning('ledger.payment_captured_without_provider', [
                'payment_id' => $payment->public_id,
                'order_id' => $order->public_id,
            ]);

            return;
        }

        $snapshot = $order->pricing_snapshot;
        $grossAmount = $payment->amount;
        $platformCommission = (int) ($snapshot['platform_service_fee'] ?? 0);
        $tax = (int) ($snapshot['vat_amount'] ?? 0);
        $gatewayFee = $payment->method === PaymentMethod::Cash
            ? 0
            : (int) round($grossAmount * (float) config('services.payments.gateway_fee_percentage', 0));

        $this->createEntry($order->id, $payment->id, $providerId, LedgerEntryType::CustomerPayment, LedgerEntryDirection::Credit, $grossAmount);

        if ($platformCommission > 0) {
            $this->createEntry($order->id, $payment->id, $providerId, LedgerEntryType::PlatformCommission, LedgerEntryDirection::Debit, $platformCommission);
        }

        if ($gatewayFee > 0) {
            $this->createEntry($order->id, $payment->id, $providerId, LedgerEntryType::GatewayFee, LedgerEntryDirection::Debit, $gatewayFee);
        }

        // Assumes pricing_snapshot.discount is 0 — true for every order
        // today, since no coupon system exists (see docs/PRICING_ENGINE.md).
        // Revisit this formula's discount attribution once one does.
        if ($payment->method === PaymentMethod::Cash) {
            $netProviderAmount = -($platformCommission + $tax);
        } else {
            $netProviderAmount = $grossAmount - $platformCommission - $gatewayFee - $tax;
        }

        $this->createEntry(
            $order->id,
            $payment->id,
            $providerId,
            LedgerEntryType::ProviderPayable,
            $netProviderAmount >= 0 ? LedgerEntryDirection::Credit : LedgerEntryDirection::Debit,
            abs($netProviderAmount),
        );
    }

    private function createEntry(
        int $orderId,
        int $paymentId,
        int $providerId,
        LedgerEntryType $type,
        LedgerEntryDirection $direction,
        int $amount,
    ): void {
        $entry = new LedgerEntry([
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'provider_id' => $providerId,
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
        ]);
        $entry->save();
    }
}
