<?php

namespace App\Domain\Dispatch\Actions;

use App\Domain\Dispatch\Contracts\NearbyTowTruckFinder;
use App\Domain\Dispatch\Events\DispatchOfferCreated;
use App\Domain\Dispatch\Exceptions\DispatchException;
use App\Domain\Dispatch\Models\DispatchOffer;
use App\Domain\Fleet\Enums\ServiceCapability;
use App\Domain\Maps\DataTransferObjects\Coordinates;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderStateMachine;
use Illuminate\Support\Facades\DB;

/**
 * Runs dispatch for an order starting at the given wave: walks forward
 * through configured waves (widening radius/candidate count each time —
 * see docs/DISPATCH_ENGINE.md §Dispatch waves and config/dispatch.php)
 * until one produces at least one eligible candidate, offering the order
 * to each of them, or flags `manual_dispatch_required` once every
 * configured wave is exhausted with zero candidates. A wave that finds no
 * candidates creates no offer rows and is not itself persisted as "the"
 * wave — only the wave that actually produced offers (or the final empty
 * one) is recorded on `orders.current_dispatch_wave`.
 *
 * Called right after order creation (wave 1, via
 * App\Domain\Dispatch\Listeners\StartDispatchListener), after a driver
 * rejects the last pending offer in a wave, and by the stale-offer
 * escalation command for later waves.
 */
class DispatchOrderAction
{
    public function __construct(
        private readonly NearbyTowTruckFinder $finder,
        private readonly OrderStateMachine $stateMachine,
    ) {}

    /**
     * @throws DispatchException
     */
    public function handle(Order $order, int $startWave = 1): void
    {
        if (! in_array($order->status, [OrderStatus::Pending, OrderStatus::SearchingProvider], true)) {
            throw DispatchException::orderNotDispatchable();
        }

        DB::transaction(function () use ($order, $startWave): void {
            $alreadyOfferedTowTruckIds = array_values(array_map(
                'intval',
                DispatchOffer::query()->where('order_id', $order->id)->pluck('tow_truck_id')->all(),
            ));

            $wave = $startWave;
            $candidates = [];

            while (true) {
                /** @var array{radius_meters: int, candidates: int}|null $waveConfig */
                $waveConfig = config("dispatch.waves.{$wave}");

                if ($waveConfig === null) {
                    $order->current_dispatch_wave = max(1, $wave - 1);
                    $order->manual_dispatch_required = true;
                    $order->save();

                    if ($order->status === OrderStatus::Pending) {
                        $this->stateMachine->transition($order, OrderStatus::SearchingProvider);
                    }

                    return;
                }

                $candidates = $this->finder->find(
                    origin: new Coordinates((float) $order->pickup_latitude, (float) $order->pickup_longitude),
                    serviceType: ServiceCapability::from($order->service_type),
                    radiusMeters: $waveConfig['radius_meters'],
                    limit: $waveConfig['candidates'],
                    excludeTowTruckIds: $alreadyOfferedTowTruckIds,
                );

                if ($candidates !== []) {
                    break;
                }

                $wave++;
            }

            $expiresAt = now()->addSeconds((int) config('dispatch.offer_ttl_seconds'));

            foreach ($candidates as $candidate) {
                $offer = new DispatchOffer([
                    'order_id' => $order->id,
                    'tow_truck_id' => $candidate->towTruck->id,
                    'driver_id' => $candidate->towTruck->driver_id,
                    'provider_id' => $candidate->towTruck->provider_id,
                    'wave' => $wave,
                    'distance_meters' => $candidate->distanceMeters,
                    'expires_at' => $expiresAt,
                ]);
                $offer->save();

                // ShouldDispatchAfterCommit defers this until the
                // enclosing transaction actually commits — see the
                // event's docblock.
                DispatchOfferCreated::dispatch($offer);
            }

            $order->current_dispatch_wave = $wave;
            $order->manual_dispatch_required = false;
            $order->save();

            if ($order->status === OrderStatus::Pending) {
                $this->stateMachine->transition($order, OrderStatus::SearchingProvider);
            }
        });
    }
}
