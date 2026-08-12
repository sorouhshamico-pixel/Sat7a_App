<?php

namespace App\Domain\Orders\Events;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on the order's private channel every time
 * App\Domain\Orders\Services\OrderStateMachine records a transition — the
 * realtime counterpart to the order_status_history row (see
 * docs/ARCHITECTURE.md §4 and docs/DISPATCH_ENGINE.md). Implements
 * ShouldDispatchAfterCommit because OrderStateMachine::transition() is
 * frequently called from inside another action's own DB transaction
 * (dispatch acceptance, manual assignment, ...) — broadcasting must never
 * fire for a change that could still roll back.
 */
class OrderStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("orders.{$this->order->public_id}")];
    }

    public function broadcastAs(): string
    {
        return 'order.status_changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->public_id,
            'from_status' => $this->from->value,
            'to_status' => $this->to->value,
            'changed_at' => now()->toIso8601String(),
        ];
    }
}
