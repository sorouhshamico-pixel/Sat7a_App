<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\DataTransferObjects\PaymentGatewayResult;
use App\Domain\Payments\DataTransferObjects\RefundGatewayResult;
use App\Domain\Payments\DataTransferObjects\WebhookEvent;
use App\Domain\Payments\Models\Payment;
use Illuminate\Http\Request;

/**
 * Business/domain code never talks to a payment gateway SDK directly —
 * see docs/PAYMENT_ARCHITECTURE.md §Abstraction. `createPayment` covers
 * this platform's whole charge flow (initiate → gateway-hosted checkout →
 * webhook confirms capture); there's no separate `authorize` step in this
 * interface — deliberately narrower than the Phase 0 design sketch, since
 * nothing in this product needs a hold-then-capture-later flow, and
 * `capture` alone already covers a gateway that confirms synchronously.
 */
interface PaymentGateway
{
    public function createPayment(Payment $payment): PaymentGatewayResult;

    public function capture(Payment $payment): PaymentGatewayResult;

    public function refund(Payment $payment, int $amountMinorUnits): RefundGatewayResult;

    public function verifyWebhookSignature(Request $request): bool;

    public function parseWebhookEvent(Request $request): WebhookEvent;

    public function getPaymentStatus(Payment $payment): PaymentGatewayResult;
}
