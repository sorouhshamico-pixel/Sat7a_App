<?php

namespace App\Domain\Dispatch\Listeners;

use App\Domain\Dispatch\Actions\DispatchOrderAction;
use App\Domain\Orders\Events\OrderCreated;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Starts wave 1 automatically right after an order is created. A dispatch
 * failure must never break order creation itself — the customer's order
 * still exists and is visible; a failed/empty search just means an
 * operations dispatcher needs to step in (see docs/TESTING_STRATEGY.md
 * §Failure-mode testing and docs/DISPATCH_ENGINE.md §Manual fallback).
 */
class StartDispatchListener
{
    public function __construct(private readonly DispatchOrderAction $action) {}

    public function handle(OrderCreated $event): void
    {
        try {
            $this->action->handle($event->order);
        } catch (Throwable $e) {
            Log::error('dispatch.start_failed', [
                'order_id' => $event->order->public_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
