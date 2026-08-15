<?php

namespace App\Domain\Payments\Adapters\Fake;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\DataTransferObjects\PaymentGatewayResult;
use App\Domain\Payments\DataTransferObjects\RefundGatewayResult;
use App\Domain\Payments\DataTransferObjects\WebhookEvent;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Payments\Exceptions\PaymentException;
use App\Domain\Payments\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Used whenever `PAYMENT_GATEWAY_DRIVER` isn't a real gateway (local dev,
 * CI, and — since no real gateway account exists yet — production too;
 * see docs/SECURITY.md §Secrets). `cash` "pays" instantly, since it's
 * collected in person and there's no real checkout to run; card methods
 * come back `pending` with a fake checkout URL, exercising the exact same
 * webhook-confirms-capture flow a real gateway would use — including a
 * genuine HMAC signature check, not a stub that always returns true.
 */
class FakePaymentGateway implements PaymentGateway
{
    public function createPayment(Payment $payment): PaymentGatewayResult
    {
        $gatewayPaymentId = 'fake_'.Str::lower(Str::random(24));

        if ($payment->method === PaymentMethod::Cash) {
            return new PaymentGatewayResult(
                gatewayPaymentId: $gatewayPaymentId,
                status: PaymentStatus::Captured,
            );
        }

        return new PaymentGatewayResult(
            gatewayPaymentId: $gatewayPaymentId,
            status: PaymentStatus::Pending,
            redirectUrl: "https://fake-gateway.test/checkout/{$gatewayPaymentId}",
            cardBrand: $payment->method->value,
            cardLastFour: '4242',
        );
    }

    public function capture(Payment $payment): PaymentGatewayResult
    {
        return new PaymentGatewayResult(
            gatewayPaymentId: (string) $payment->gateway_payment_id,
            status: PaymentStatus::Captured,
        );
    }

    public function refund(Payment $payment, int $amountMinorUnits): RefundGatewayResult
    {
        return new RefundGatewayResult(
            gatewayRefundId: 'fake_refund_'.Str::lower(Str::random(24)),
            status: RefundStatus::Succeeded,
        );
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Fake-Signature');

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $this->webhookSecret());

        return hash_equals($expected, $signature);
    }

    public function parseWebhookEvent(Request $request): WebhookEvent
    {
        $payload = $request->json()->all();

        $status = is_string($payload['status'] ?? null) ? PaymentStatus::tryFrom($payload['status']) : null;

        if ($status === null || ! is_string($payload['event_id'] ?? null) || ! is_string($payload['gateway_payment_id'] ?? null)) {
            throw PaymentException::malformedWebhookPayload();
        }

        return new WebhookEvent(
            eventId: $payload['event_id'],
            eventType: is_string($payload['event_type'] ?? null) ? $payload['event_type'] : 'payment.updated',
            gatewayPaymentId: $payload['gateway_payment_id'],
            status: $status,
            rawPayload: $payload,
            failureReason: is_string($payload['failure_reason'] ?? null) ? $payload['failure_reason'] : null,
            cardBrand: is_string($payload['card_brand'] ?? null) ? $payload['card_brand'] : null,
            cardLastFour: is_string($payload['card_last_four'] ?? null) ? $payload['card_last_four'] : null,
        );
    }

    public function getPaymentStatus(Payment $payment): PaymentGatewayResult
    {
        // Stateless fake — there's no real remote gateway to poll, so this
        // just reflects the payment's own last known local status back.
        return new PaymentGatewayResult(
            gatewayPaymentId: (string) $payment->gateway_payment_id,
            status: $payment->status,
            cardBrand: $payment->card_brand,
            cardLastFour: $payment->card_last_four,
            failureReason: $payment->failure_reason,
        );
    }

    private function webhookSecret(): string
    {
        return (string) config('services.payments.fake.webhook_secret');
    }
}
