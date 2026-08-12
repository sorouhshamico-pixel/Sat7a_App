<?php

namespace App\Domain\Dispatch\Actions;

use App\Domain\Dispatch\Enums\DispatchOfferStatus;
use App\Domain\Dispatch\Exceptions\DispatchException;
use App\Domain\Dispatch\Models\DispatchOffer;
use App\Domain\Orders\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A driver declining is a real, immediate signal — rather than waiting for
 * the offer's TTL to expire, if this was the last pending offer in its
 * wave the next wave starts right away (see docs/DISPATCH_ENGINE.md
 * §Dispatch waves). A stale (never-responded) offer is instead handled by
 * the scheduled escalation command
 * (App\Domain\Dispatch\Services\DispatchEscalationService).
 */
class RejectDispatchOfferAction
{
    public function __construct(private readonly DispatchOrderAction $dispatchOrderAction) {}

    /**
     * @throws DispatchException
     */
    public function handle(DispatchOffer $offer): void
    {
        $order = DB::transaction(function () use ($offer) {
            /** @var DispatchOffer $freshOffer */
            $freshOffer = DispatchOffer::query()->lockForUpdate()->findOrFail($offer->id);

            if ($freshOffer->status !== DispatchOfferStatus::Pending) {
                throw DispatchException::offerNoLongerAvailable();
            }

            $freshOffer->status = DispatchOfferStatus::Rejected;
            $freshOffer->responded_at = now();
            $freshOffer->save();

            $order = $freshOffer->order()->lockForUpdate()->first();

            $remainingPending = DispatchOffer::query()
                ->where('order_id', $freshOffer->order_id)
                ->where('wave', $freshOffer->wave)
                ->where('status', DispatchOfferStatus::Pending)
                ->count();

            return $remainingPending === 0 ? $order : null;
        });

        if ($order === null || $order->status !== OrderStatus::SearchingProvider) {
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
