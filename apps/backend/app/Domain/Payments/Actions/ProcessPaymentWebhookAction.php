<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Exceptions\PaymentException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentWebhookEvent;
use App\Domain\Payments\Services\PaymentStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * See docs/PAYMENT_ARCHITECTURE.md §Webhooks: every handler verifies
 * signature, is idempotent per event ID, and logs the raw payload
 * redacted of sensitive values. Always returns normally (never throws)
 * once past signature/payload validation — an unrecognized
 * `gateway_payment_id` or an out-of-order/non-transitionable status is
 * logged and the event is still marked processed, never surfaced as an
 * error back to the gateway, which would just trigger pointless retries.
 */
class ProcessPaymentWebhookAction
{
    public function __construct(private readonly PaymentStateMachine $stateMachine) {}

    /**
     * @throws PaymentException
     */
    public function handle(PaymentGateway $gateway, string $gatewayName, Request $request): void
    {
        if (! $gateway->verifyWebhookSignature($request)) {
            throw PaymentException::webhookSignatureInvalid();
        }

        $event = $gateway->parseWebhookEvent($request);

        $alreadyProcessed = PaymentWebhookEvent::query()
            ->where('gateway', $gatewayName)
            ->where('event_id', $event->eventId)
            ->exists();

        if ($alreadyProcessed) {
            return;
        }

        $webhookEvent = new PaymentWebhookEvent([
            'gateway' => $gatewayName,
            'event_id' => $event->eventId,
            'event_type' => $event->eventType,
            'payload' => $this->redact($event->rawPayload),
        ]);
        $webhookEvent->save();

        $payment = Payment::query()->where('gateway_payment_id', $event->gatewayPaymentId)->first();

        if ($payment === null) {
            Log::warning('payments.webhook_unknown_payment', [
                'gateway' => $gatewayName,
                'gateway_payment_id' => $event->gatewayPaymentId,
            ]);
        } elseif ($payment->status->canTransitionTo($event->status)) {
            $this->stateMachine->transition(
                $payment,
                $event->status,
                failureReason: $event->failureReason,
                cardBrand: $event->cardBrand,
                cardLastFour: $event->cardLastFour,
            );
        } else {
            Log::warning('payments.webhook_non_transitionable_status', [
                'gateway' => $gatewayName,
                'payment_id' => $payment->public_id,
                'from_status' => $payment->status->value,
                'to_status' => $event->status->value,
            ]);
        }

        $webhookEvent->processed_at = now();
        $webhookEvent->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redact(array $payload): array
    {
        $sensitiveKeys = ['card_number', 'pan', 'cvv', 'cvc'];

        foreach ($sensitiveKeys as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[redacted]';
            }
        }

        return $payload;
    }
}
