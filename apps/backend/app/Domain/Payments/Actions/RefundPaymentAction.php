<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Payments\Exceptions\PaymentException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\Refund;
use App\Domain\Payments\Services\PaymentStateMachine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin/finance-initiated only — see docs/PAYMENT_ARCHITECTURE.md.
 * Automatic refund-on-cancellation is
 * deliberately not wired up: `orders.cancellation_fee` has been `0` for
 * every order since Phase 8, with no cancellation-fee policy defined yet
 * (see docs/ORDER_LIFECYCLE.md) — auto-refunding the full amount would be
 * a business decision this project hasn't actually made, so a human
 * decides for now.
 */
class RefundPaymentAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PaymentStateMachine $stateMachine,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws PaymentException
     */
    public function handle(Payment $payment, int $amountMinorUnits, User $actor, ?string $reason = null): Refund
    {
        if (! $payment->status->isRefundable()) {
            throw PaymentException::notRefundable();
        }

        $available = $payment->amount - $payment->refundedAmount();

        if ($amountMinorUnits <= 0 || $amountMinorUnits > $available) {
            throw PaymentException::refundExceedsAvailableAmount();
        }

        $result = $this->gateway->refund($payment, $amountMinorUnits);

        return DB::transaction(function () use ($payment, $amountMinorUnits, $actor, $reason, $result, $available): Refund {
            $refund = new Refund([
                'amount' => $amountMinorUnits,
                'reason' => $reason,
                'status' => $result->status,
                'gateway_refund_id' => $result->gatewayRefundId,
                'failure_reason' => $result->failureReason,
            ]);
            $refund->payment_id = $payment->id;
            $refund->initiated_by = $actor->id;
            $refund->save();

            if ($result->status === RefundStatus::Succeeded) {
                $remaining = $available - $amountMinorUnits;
                $this->stateMachine->transition(
                    $payment,
                    $remaining > 0 ? PaymentStatus::PartiallyRefunded : PaymentStatus::Refunded,
                );
            }

            $this->auditLogger->log(
                actor: $actor,
                action: 'payments.refunded',
                entityType: 'payment',
                entityId: $payment->public_id,
                newValues: ['amount' => $amountMinorUnits, 'status' => $result->status->value],
                reason: $reason,
            );

            return $refund;
        });
    }
}
