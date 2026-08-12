<?php

namespace App\Domain\Orders\Events;

use App\Domain\Orders\Enums\OrderCancelledBy;
use App\Domain\Orders\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

class OrderCancelled
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly OrderCancelledBy $cancelledBy,
        public readonly ?string $reason,
    ) {}
}
