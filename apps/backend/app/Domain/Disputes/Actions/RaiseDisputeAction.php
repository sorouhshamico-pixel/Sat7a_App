<?php

namespace App\Domain\Disputes\Actions;

use App\Domain\Disputes\Enums\DisputeReason;
use App\Domain\Disputes\Enums\DisputeStatus;
use App\Domain\Disputes\Exceptions\DisputeException;
use App\Domain\Disputes\Models\Dispute;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;

/**
 * A customer raises a dispute on their own order — only once the order has
 * reached a terminal state (`completed` or any `cancelled_*`), since
 * there's nothing to dispute about a trip still in progress. At most one
 * non-terminal (`open`/`under_review`) dispute may exist per order at a
 * time; a resolved/rejected dispute doesn't block raising a new one.
 */
class RaiseDisputeAction
{
    private const DISPUTABLE_STATUSES = [
        OrderStatus::Completed,
        OrderStatus::CancelledByCustomer,
        OrderStatus::CancelledByProvider,
        OrderStatus::CancelledByAdmin,
    ];

    /**
     * @throws DisputeException
     */
    public function handle(Order $order, DisputeReason $reason, string $description): Dispute
    {
        if (! in_array($order->status, self::DISPUTABLE_STATUSES, true)) {
            throw DisputeException::orderNotDisputable();
        }

        $hasOpenDispute = Dispute::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [DisputeStatus::Open->value, DisputeStatus::UnderReview->value])
            ->exists();

        if ($hasOpenDispute) {
            throw DisputeException::alreadyOpen();
        }

        $dispute = new Dispute([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'reason' => $reason,
            'description' => $description,
            'status' => DisputeStatus::Open,
        ]);
        $dispute->save();

        return $dispute;
    }
}
