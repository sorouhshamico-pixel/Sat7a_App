<?php

namespace App\Domain\Dispatch\Events;

use App\Domain\Dispatch\Models\DispatchOffer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushed to the offered driver's private channel the moment
 * App\Domain\Dispatch\Actions\DispatchOrderAction creates the offer —
 * this is what lets a driver's app show a new job without polling
 * `GET /api/v1/drivers/me/dispatch-offers` (see docs/DISPATCH_ENGINE.md).
 * ShouldDispatchAfterCommit for the same reason as OrderStatusChanged:
 * DispatchOrderAction runs inside its own DB transaction.
 */
class DispatchOfferCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly DispatchOffer $offer) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("drivers.{$this->offer->driver->public_id}")];
    }

    public function broadcastAs(): string
    {
        return 'dispatch.offer_created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'offer_id' => $this->offer->public_id,
            'order_id' => $this->offer->order->public_id,
            'wave' => $this->offer->wave,
            'distance_meters' => $this->offer->distance_meters,
            'expires_at' => $this->offer->expires_at->toIso8601String(),
        ];
    }
}
