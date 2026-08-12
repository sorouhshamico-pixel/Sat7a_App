<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\Vehicle;
use App\Domain\Maps\Contracts\RoutingProvider;
use App\Domain\Maps\DataTransferObjects\Coordinates;
use App\Domain\Orders\Enums\OrderPaymentMethod;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderCreated;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderStateMachine;
use App\Domain\Pricing\Actions\GenerateQuoteAction;

/**
 * The distance and price are always recomputed here from the pickup/
 * dropoff coordinates — a client-supplied price or distance is never
 * trusted, even if it matches what `/pricing/quote` previously returned
 * for the same trip (see docs/SECURITY.md and docs/PRICING_ENGINE.md).
 */
class CreateOrderAction
{
    public function __construct(
        private readonly RoutingProvider $routingProvider,
        private readonly GenerateQuoteAction $generateQuote,
        private readonly OrderStateMachine $stateMachine,
    ) {}

    public function handle(
        Customer $customer,
        Vehicle $vehicle,
        string $serviceType,
        string $vehicleCategory,
        Coordinates $pickup,
        string $pickupFormattedAddress,
        Coordinates $dropoff,
        string $dropoffFormattedAddress,
        ?string $notes = null,
    ): Order {
        $route = $this->routingProvider->route($pickup, $dropoff);

        $snapshot = $this->generateQuote->handle(
            distanceMeters: $route->distanceMeters,
            serviceType: $serviceType,
            vehicleCategory: $vehicleCategory,
        );

        $order = new Order([
            'service_type' => $serviceType,
            'pickup_latitude' => $pickup->latitude,
            'pickup_longitude' => $pickup->longitude,
            'pickup_formatted_address' => $pickupFormattedAddress,
            'dropoff_latitude' => $dropoff->latitude,
            'dropoff_longitude' => $dropoff->longitude,
            'dropoff_formatted_address' => $dropoffFormattedAddress,
            'notes' => $notes,
            'pricing_snapshot' => $snapshot->toArray(),
            'quoted_price' => $snapshot->total,
            'payment_method' => OrderPaymentMethod::Cash,
        ]);
        $order->customer_id = $customer->id;
        $order->vehicle_id = $vehicle->id;
        $order->status = OrderStatus::Pending;
        $order->save();

        $this->stateMachine->recordInitial($order);

        OrderCreated::dispatch($order);

        return $order;
    }
}
