<?php

namespace App\Domain\Tracking\Actions;

use App\Domain\Drivers\Models\Driver;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Tracking\Events\OrderLocationUpdated;
use App\Domain\Tracking\Models\OrderLocationPing;
use Illuminate\Support\Carbon;

/**
 * A driver's app calls this continuously, independent of whether they
 * currently have an order — it's what keeps `tow_trucks.current_latitude`/
 * `current_longitude` fresh for dispatch's nearby-candidate search (see
 * docs/DISPATCH_ENGINE.md), which had no real update path before this
 * phase. When the driver also has an order in an active trip status, the
 * same ping is additionally recorded as a breadcrumb against that order
 * and broadcast live (see docs/LIVE_LOCATION_TRACKING.md).
 */
class RecordLocationPingAction
{
    /**
     * Statuses during which a driver's position is worth recording
     * against the order — before `provider_assigned` there's no truck to
     * track yet, and from `completed` onward the trip is over.
     *
     * @return list<OrderStatus>
     */
    private const TRACKABLE_STATUSES = [
        OrderStatus::ProviderAssigned,
        OrderStatus::ProviderEnRoute,
        OrderStatus::ProviderArrived,
        OrderStatus::VehicleLoading,
        OrderStatus::TripStarted,
        OrderStatus::InTransit,
    ];

    public function handle(
        Driver $driver,
        float $latitude,
        float $longitude,
        ?int $heading = null,
        ?int $speedKmh = null,
        ?Carbon $recordedAt = null,
    ): ?OrderLocationPing {
        $recordedAt ??= now();

        $driver->towTruck?->update([
            'current_latitude' => $latitude,
            'current_longitude' => $longitude,
            'last_location_at' => $recordedAt,
        ]);

        $activeOrder = Order::query()
            ->where('assigned_driver_id', $driver->id)
            ->whereIn('status', self::TRACKABLE_STATUSES)
            ->first();

        if ($activeOrder === null) {
            return null;
        }

        $ping = new OrderLocationPing([
            'order_id' => $activeOrder->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'heading' => $heading,
            'speed_kmh' => $speedKmh,
            'recorded_at' => $recordedAt,
        ]);
        $ping->save();

        OrderLocationUpdated::dispatch($ping);

        return $ping;
    }
}
