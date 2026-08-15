<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\DataTransferObjects\PaymentInitiation;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Exceptions\PaymentException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\PaymentStateMachine;

/**
 * A client-supplied amount is never trusted — the charge is always the
 * order's `final_price` (or `quoted_price` before that's set), same
 * "server recomputes, never trusts the client" rule as
 * App\Domain\Orders\Actions\CreateOrderAction.
 */
class CreatePaymentAction
{
    /**
     * Statuses that represent an already-succeeded or still-in-flight
     * payment — a new attempt must never be created while one of these
     * exists for the order. A `failed`/`cancelled` prior attempt does not
     * block a retry.
     *
     * @var list<PaymentStatus>
     */
    private const BLOCKING_STATUSES = [
        PaymentStatus::Pending,
        PaymentStatus::Authorized,
        PaymentStatus::Captured,
        PaymentStatus::PartiallyRefunded,
        PaymentStatus::Refunded,
    ];

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PaymentStateMachine $stateMachine,
    ) {}

    /**
     * @throws PaymentException
     */
    public function handle(Order $order, Customer $customer, PaymentMethod $method, ?string $idempotencyKey = null): PaymentInitiation
    {
        if ($idempotencyKey !== null) {
            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                return new PaymentInitiation($existing, null);
            }
        }

        if (! $order->status->isPayable()) {
            throw PaymentException::orderNotPayable();
        }

        $hasBlockingPayment = Payment::query()
            ->where('order_id', $order->id)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->exists();

        if ($hasBlockingPayment) {
            throw PaymentException::paymentAlreadyActive();
        }

        $payment = new Payment([
            'gateway' => config('services.payments.driver', 'fake'),
            'method' => $method,
            'amount' => $order->final_price ?? $order->quoted_price,
            'currency' => 'SAR',
            'idempotency_key' => $idempotencyKey,
        ]);
        $payment->order_id = $order->id;
        $payment->customer_id = $customer->id;
        $payment->status = PaymentStatus::Pending;
        $payment->save();

        $result = $this->gateway->createPayment($payment);

        $payment->gateway_payment_id = $result->gatewayPaymentId;
        $payment->save();

        if ($result->status !== PaymentStatus::Pending) {
            $payment = $this->stateMachine->transition(
                $payment,
                $result->status,
                failureReason: $result->failureReason,
                cardBrand: $result->cardBrand,
                cardLastFour: $result->cardLastFour,
            );
        } elseif ($result->cardBrand !== null || $result->cardLastFour !== null) {
            $payment->card_brand = $result->cardBrand;
            $payment->card_last_four = $result->cardLastFour;
            $payment->save();
        }

        return new PaymentInitiation($payment, $result->redirectUrl);
    }
}
