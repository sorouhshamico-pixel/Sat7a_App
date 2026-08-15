<?php

namespace App\Domain\Orders\Events;

use App\Domain\Orders\Enums\OrderCancelledBy;
use App\Domain\Orders\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Implements ShouldDispatchAfterCommit because
 * App\Domain\Orders\Actions\CancelOrderAction dispatches this from inside
 * its own DB transaction — a listener with real side effects (Phase 16's
 * notification send) must never fire for a cancellation that could still
 * roll back. Same reasoning as every ShouldDispatchAfterCommit event in
 * the Payments/Ledger domains.
 */
class OrderCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly OrderCancelledBy $cancelledBy,
        public readonly ?string $reason,
    ) {}
}
