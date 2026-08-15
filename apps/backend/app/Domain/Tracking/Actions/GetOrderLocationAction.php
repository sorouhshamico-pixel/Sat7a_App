<?php

namespace App\Domain\Tracking\Actions;

use App\Domain\Orders\Models\Order;
use App\Domain\Tracking\Models\OrderLocationPing;
use Illuminate\Support\Collection;

/**
 * Shared by the customer and admin "where's my tow truck" endpoints (see
 * docs/LIVE_LOCATION_TRACKING.md). Falls back to the assigned tow truck's
 * last known general position when no trip ping exists yet — e.g. right
 * after acceptance, before the driver's app has sent its first update.
 */
class GetOrderLocationAction
{
    private const MAX_PATH_POINTS = 500;

    /**
     * @return array{current: array<string, mixed>|null, path: Collection<int, OrderLocationPing>}
     */
    public function handle(Order $order, int $pathLimit = 200): array
    {
        $pathLimit = min(max($pathLimit, 1), self::MAX_PATH_POINTS);

        $latestPing = OrderLocationPing::query()
            ->where('order_id', $order->id)
            ->orderByDesc('recorded_at')
            ->first();

        $path = OrderLocationPing::query()
            ->where('order_id', $order->id)
            ->orderByDesc('recorded_at')
            ->limit($pathLimit)
            ->get()
            ->sortBy('recorded_at')
            ->values();

        if ($latestPing !== null) {
            $current = [
                'latitude' => (float) $latestPing->latitude,
                'longitude' => (float) $latestPing->longitude,
                'heading' => $latestPing->heading,
                'speed_kmh' => $latestPing->speed_kmh,
                'recorded_at' => $latestPing->recorded_at,
                'source' => 'trip_ping',
            ];
        } else {
            $truck = $order->assignedTowTruck;
            $current = ($truck === null || $truck->current_latitude === null) ? null : [
                'latitude' => (float) $truck->current_latitude,
                'longitude' => (float) $truck->current_longitude,
                'heading' => null,
                'speed_kmh' => null,
                'recorded_at' => $truck->last_location_at,
                'source' => 'tow_truck_last_known',
            ];
        }

        return ['current' => $current, 'path' => $path];
    }
}
