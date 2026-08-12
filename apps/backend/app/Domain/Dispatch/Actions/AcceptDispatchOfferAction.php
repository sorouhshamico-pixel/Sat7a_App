<?php

namespace App\Domain\Dispatch\Actions;

use App\Domain\Dispatch\Enums\DispatchOfferStatus;
use App\Domain\Dispatch\Exceptions\DispatchException;
use App\Domain\Dispatch\Models\DispatchOffer;
use App\Domain\Fleet\Enums\TowTruckStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderStateMachine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Acceptance must never let two drivers accept the same order — see
 * docs/DISPATCH_ENGINE.md §Concurrency. Row-locks the order (and the
 * offer) inside a transaction, re-verifies both are still in an
 * acceptable state after the lock is acquired, then assigns and closes
 * out every other pending offer for the order in the same transaction.
 */
class AcceptDispatchOfferAction
{
    public function __construct(private readonly OrderStateMachine $stateMachine) {}

    /**
     * @throws DispatchException
     */
    public function handle(DispatchOffer $offer, User $actor): Order
    {
        return DB::transaction(function () use ($offer, $actor): Order {
            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($offer->order_id);

            /** @var DispatchOffer $freshOffer */
            $freshOffer = DispatchOffer::query()->lockForUpdate()->findOrFail($offer->id);

            if ($freshOffer->status !== DispatchOfferStatus::Pending || $order->status !== OrderStatus::SearchingProvider) {
                throw DispatchException::offerNoLongerAvailable();
            }

            if ($freshOffer->expires_at->isPast()) {
                $freshOffer->status = DispatchOfferStatus::Expired;
                $freshOffer->responded_at = now();
                $freshOffer->save();

                throw DispatchException::offerNoLongerAvailable();
            }

            $order->assigned_provider_id = $freshOffer->provider_id;
            $order->assigned_driver_id = $freshOffer->driver_id;
            $order->assigned_tow_truck_id = $freshOffer->tow_truck_id;
            $order->accepted_at = now();
            $order->save();

            $order = $this->stateMachine->transition($order, OrderStatus::ProviderAssigned, $actor);

            $freshOffer->status = DispatchOfferStatus::Accepted;
            $freshOffer->responded_at = now();
            $freshOffer->save();

            DispatchOffer::query()
                ->where('order_id', $order->id)
                ->where('id', '!=', $freshOffer->id)
                ->where('status', DispatchOfferStatus::Pending)
                ->update(['status' => DispatchOfferStatus::Superseded, 'responded_at' => now()]);

            $freshOffer->towTruck()->first()?->update(['status' => TowTruckStatus::Reserved]);

            return $order;
        });
    }
}
