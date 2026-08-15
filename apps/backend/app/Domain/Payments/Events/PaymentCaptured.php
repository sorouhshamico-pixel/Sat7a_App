<?php

namespace App\Domain\Payments\Events;

use App\Domain\Payments\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on the same per-order channel Phase 10/11 already use — see
 * docs/REALTIME.md. Dispatched from
 * App\Domain\Payments\Services\PaymentStateMachine, the single place a
 * payment's status ever changes.
 */
class PaymentCaptured implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Payment $payment) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("orders.{$this->payment->order->public_id}")];
    }

    public function broadcastAs(): string
    {
        return 'payment.captured';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'payment_id' => $this->payment->public_id,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
            'method' => $this->payment->method->value,
        ];
    }
}
