<?php

namespace App\Domain\Dispatch\Services;

use App\Domain\Dispatch\Actions\DispatchOrderAction;
use App\Domain\Dispatch\Enums\DispatchOfferStatus;
use App\Domain\Dispatch\Exceptions\DispatchException;
use App\Domain\Dispatch\Models\DispatchOffer;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Run periodically (see `dispatch:escalate-stale-offers`, scheduled every
 * minute in routes/console.php). Expires offers a driver never responded
 * to within the TTL, then — for any order whose current wave has no
 * pending offers left — starts the next wave, or flags
 * `manual_dispatch_required` once waves are exhausted (see
 * docs/DISPATCH_ENGINE.md §Dispatch waves and §Manual fallback).
 */
class DispatchEscalationService
{
    public function __construct(private readonly DispatchOrderAction $dispatchOrderAction) {}

    public function run(): int
    {
        $staleOrderIds = DispatchOffer::query()
            ->where('status', DispatchOfferStatus::Pending)
            ->where('expires_at', '<', now())
            ->pluck('order_id')
            ->unique();

        $expiredCount = DispatchOffer::query()
            ->where('status', DispatchOfferStatus::Pending)
            ->where('expires_at', '<', now())
            ->update(['status' => DispatchOfferStatus::Expired, 'responded_at' => now()]);

        foreach ($staleOrderIds as $orderId) {
            $this->escalateIfWaveExhausted((int) $orderId);
        }

        return $expiredCount;
    }

    private function escalateIfWaveExhausted(int $orderId): void
    {
        $order = Order::query()->find($orderId);

        if ($order === null || $order->status !== OrderStatus::SearchingProvider) {
            return;
        }

        $remainingPending = DispatchOffer::query()
            ->where('order_id', $orderId)
            ->where('wave', $order->current_dispatch_wave)
            ->where('status', DispatchOfferStatus::Pending)
            ->count();

        if ($remainingPending > 0) {
            return;
        }

        try {
            $this->dispatchOrderAction->handle($order, $order->current_dispatch_wave + 1);
        } catch (DispatchException $e) {
            Log::warning('dispatch.escalation_failed', [
                'order_id' => $order->public_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
