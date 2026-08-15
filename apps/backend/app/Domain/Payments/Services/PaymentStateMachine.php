<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Events\PaymentCaptured;
use App\Domain\Payments\Events\PaymentFailed;
use App\Domain\Payments\Exceptions\PaymentException;
use App\Domain\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * The single place a payment's status ever changes — mirrors
 * App\Domain\Orders\Services\OrderStateMachine. Validates against
 * App\Domain\Payments\Enums\PaymentStatus::allowedTransitions(), records
 * the right timestamp column, updates the order's `final_price` on
 * capture, and broadcasts — all in one place so
 * App\Domain\Payments\Actions\CreatePaymentAction (a synchronous cash
 * capture) and ProcessPaymentWebhookAction (an async card capture) can't
 * drift in behavior.
 */
class PaymentStateMachine
{
    /**
     * @throws PaymentException
     */
    public function transition(
        Payment $payment,
        PaymentStatus $to,
        ?string $failureReason = null,
        ?string $cardBrand = null,
        ?string $cardLastFour = null,
    ): Payment {
        $from = $payment->status;

        if (! $from->canTransitionTo($to)) {
            throw PaymentException::invalidTransition();
        }

        DB::transaction(function () use ($payment, $to, $failureReason, $cardBrand, $cardLastFour): void {
            $payment->status = $to;

            match ($to) {
                PaymentStatus::Authorized => $payment->authorized_at = now(),
                PaymentStatus::Captured => $payment->captured_at = now(),
                PaymentStatus::Failed => $payment->failed_at = now(),
                PaymentStatus::Cancelled => $payment->cancelled_at = now(),
                default => null,
            };

            if ($failureReason !== null) {
                $payment->failure_reason = $failureReason;
            }
            if ($cardBrand !== null) {
                $payment->card_brand = $cardBrand;
            }
            if ($cardLastFour !== null) {
                $payment->card_last_four = $cardLastFour;
            }

            $payment->save();

            if ($to === PaymentStatus::Captured) {
                $order = $payment->order;
                $order->final_price = $payment->amount;
                $order->save();
            }
        });

        $payment = $payment->refresh();

        match ($to) {
            PaymentStatus::Captured => PaymentCaptured::dispatch($payment),
            PaymentStatus::Failed => PaymentFailed::dispatch($payment),
            default => null,
        };

        return $payment;
    }
}
