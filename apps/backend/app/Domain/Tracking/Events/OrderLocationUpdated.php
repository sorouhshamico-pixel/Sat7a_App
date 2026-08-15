<?php

namespace App\Domain\Tracking\Events;

use App\Domain\Tracking\Models\OrderLocationPing;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on the same per-order channel Phase 10 introduced for order
 * status changes (see docs/REALTIME.md) — a live map dot for the
 * customer, provider staff, and platform ops watching the order.
 */
class OrderLocationUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly OrderLocationPing $ping) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("orders.{$this->ping->order->public_id}")];
    }

    public function broadcastAs(): string
    {
        return 'order.location_updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'latitude' => (float) $this->ping->latitude,
            'longitude' => (float) $this->ping->longitude,
            'heading' => $this->ping->heading,
            'speed_kmh' => $this->ping->speed_kmh,
            'recorded_at' => $this->ping->recorded_at->toIso8601String(),
        ];
    }
}
